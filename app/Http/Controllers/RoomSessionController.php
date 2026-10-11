<?php

namespace App\Http\Controllers;

use App\Models\AdjustmentReason;
use App\Models\AttemptSchedule;
use App\Models\ConnectionStatus;
use App\Models\EvaluationCriterion;
use App\Models\EvaluationLetterhead;
use App\Models\EvaluationSubmission;
use App\Models\PanelSubstitutionRequest;
use App\Models\PresentationAttempt;
use App\Models\PresentationDateRoom;
use App\Models\ProposedTitle;
use App\Models\RoomSession;
use App\Models\RoomTerminal;
use App\Models\Student;
use App\Models\TerminalConnection;
use App\Models\User;
use App\Services\EvaluationSubmissionService;
use App\Services\PanelAssignmentService;
use App\Services\PanelSubstitutionService;
use App\Services\PaymentVerificationService;
use App\Services\PresentationControlService;
use App\Services\RoomBreakService;
use App\Services\RoomQueuePreviewService;
use App\Services\TerminalConnectionService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The room screen, in two modes (user-directed 2026-10-11 redesign, replacing
 * the 2026-08-15 room-account + terminal-picker device claim):
 *
 *  1. Panelist mode (panelist.live-room.*, behind the panelist's own login):
 *     Live Room lists the running rooms of the panelist's college; Join takes
 *     the next free seat through TerminalConnectionService::connectToRoom(),
 *     which refuses once the room's panel count is reached. The panelist's
 *     open seat *is* their room — one room at a time — so no request carries a
 *     room id. Evaluation, the Lead's controls and substitution are re-derived
 *     from that seat on every request, exactly as before.
 *  2. Public display (room-session.*, no login): a list of running rooms any
 *     device can open to show the live queue, e.g. on a projector. This
 *     browser's session only remembers which room it shows
 *     (room_view.room_session_id). Nothing can be done from it.
 *
 * Both render the same home view and partials; $rs carries the route-name
 * prefix so the view posts to the right place.
 */
class RoomSessionController extends Controller
{
    private const PANELIST_ROUTES = 'panelist.live-room.';

    private const PUBLIC_ROUTES = 'room-session.';

    /**
     * Public display: the room list. A device already showing a room still
     * open goes straight back to it.
     */
    public function entry(Request $request, TerminalConnectionService $connections)
    {
        if ($this->watchedRoomSession($request)) {
            return redirect()->route('panelist.live-room.home');
        }

        $roomSessions = $this->openRoomSessions();

        return view('room-session.rooms', [
            'roomsByCategory' => $roomSessions->groupBy(fn ($session) => $session->presentationDateRoom->presentationDate->category->id),
            'seats' => $roomSessions->mapWithKeys(fn ($session) => [$session->id => $connections->seatSummary($session)]),
        ]);
    }

    public function join(Request $request)
    {
        $validated = $request->validate([
            'room_session_id' => ['required', 'integer'],
        ]);

        $roomSession = RoomSession::open()->find($validated['room_session_id']);

        if (! $roomSession) {
            return redirect()->route('room-session.entry')->with('error', 'That room is no longer running.');
        }

        $request->session()->put('room_view.room_session_id', $roomSession->id);

        return redirect()->route('panelist.live-room.home');
    }

    /**
     * Public display: back to the room list.
     */
    public function leave(Request $request)
    {
        $request->session()->forget('room_view.room_session_id');

        return redirect()->route('room-session.entry');
    }

    /**
     * Panelist mode: the running rooms of the panelist's college, the ones
     * they're on the panel for first.
     */
    public function liveRooms(Request $request, TerminalConnectionService $connections)
    {
        $panelist = $request->user();
        $collegeId = $panelist->panelistProfile?->college_id;

        $roomSessions = $this->openRoomSessions()
            ->filter(fn ($session) => $session->presentationDateRoom->presentationDate->category->college_id === $collegeId)
            ->values();

        $onPanel = $roomSessions->mapWithKeys(fn ($session) => [
            $session->id => $connections->eligiblePanelistIds($session)->contains($panelist->id),
        ]);

        $roomSessions = $roomSessions->sortBy(fn ($session) => $onPanel[$session->id] ? 0 : 1)->values();

        return view('panelist.live-room.index', [
            'roomSessions' => $roomSessions,
            'seats' => $roomSessions->mapWithKeys(fn ($session) => [$session->id => $connections->seatSummary($session)]),
            'onPanel' => $onPanel,
            'joinedRoomSessionId' => $this->panelistConnection($panelist)?->roomTerminal->room_session_id,
        ]);
    }

    /**
     * Panelist mode: take a seat in a room. Joining a room signs the panelist
     * out of any other room first (one room at a time); re-joining the room
     * they're already in just opens it.
     */
    public function joinAsPanelist(Request $request, TerminalConnectionService $connections)
    {
        $validated = $request->validate([
            'room_session_id' => ['required', 'integer'],
        ]);

        $panelist = $request->user();
        $roomSession = RoomSession::open()->with('presentationDateRoom.presentationDate.category')->find($validated['room_session_id']);

        if (! $roomSession || $roomSession->presentationDateRoom->presentationDate->category->college_id !== $panelist->panelistProfile?->college_id) {
            return redirect()->route('panelist.live-room.index')->with('error', 'That room is no longer running.');
        }

        $current = $this->panelistConnection($panelist);

        if ($current?->roomTerminal->room_session_id === $roomSession->id) {
            return redirect()->route('panelist.live-room.home');
        }

        $outcome = $connections->connectToRoom($roomSession, $panelist, 'ACCOUNT', $request->ip(), $request->userAgent());

        if (! $outcome['ok']) {
            return redirect()->route('panelist.live-room.index')->with('error', $outcome['error']);
        }

        if ($current) {
            $this->disconnect($current);
        }

        return redirect()->route('panelist.live-room.home');
    }

    /**
     * Panelist mode: give up the seat.
     */
    public function leaveAsPanelist(Request $request)
    {
        TerminalConnection::where('panelist_user_id', $request->user()->id)
            ->whereNull('disconnected_at')
            ->get()
            ->each(fn ($connection) => $this->disconnect($connection));

        return redirect()->route('panelist.live-room.index');
    }

    public function home(Request $request, RoomQueuePreviewService $roomQueuePreviewService)
    {
        [$roomSession, $connection] = $this->requireRoom($request);
        $data = $this->buildHomeData($request, $roomSession, $connection, $roomQueuePreviewService);

        return view('room-session.home', $data);
    }

    /**
     * JSON poller behind the room screen's live refresh: the same data as
     * home(), pre-rendered into the same partials the page uses, so the client
     * only ever does a plain DOM swap.
     */
    public function status(Request $request, RoomQueuePreviewService $roomQueuePreviewService)
    {
        $noStore = ['Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache'];

        if ($this->panelistMode($request)) {
            $connection = $this->panelistConnection($request->user());
            $roomSession = $connection?->roomTerminal->roomSession;
        } else {
            $connection = null;
            $roomSession = $this->watchedRoomSession($request);
        }

        // The room ended, or the panelist lost their seat (left, Disconnect,
        // joined elsewhere): back to the list rather than polling it.
        if (! $roomSession) {
            return response()->json([
                'redirect' => route($this->panelistMode($request) ? 'panelist.live-room.index' : 'room-session.entry'),
            ])->withHeaders($noStore);
        }

        $data = $this->buildHomeData($request, $roomSession, $connection, $roomQueuePreviewService);

        return response()->json([
            'seatsLabel' => $data['seats']['taken'] . '/' . $data['seats']['total'],
            // The client reloads the whole page the instant this flips (see
            // home.blade.php's poll()): an Admin approving a pending
            // "unassigned" request changes the panelist's role in the room.
            'classification' => $data['assignmentClassification']['kind'] ?? null,
            'sessionInfoHtml' => view('room-session.partials.control-info-card', $data)->render(),
            'sessionInfoNextHtml' => view('room-session.partials.control-info-next', $data)->render(),
            'queueHtml' => view('room-session.partials.queue-panel', $data)->render(),
            // The Current/Last Group status block for everyone, plus the
            // control buttons for the Lead only.
            'controlHtml' => view('room-session.partials.attempt-status-card', $data)->render()
                . ($data['canControlFlow']
                    ? view('room-session.partials.presentation-control', $data + ['showHeader' => (bool) $data['connection']])->render()
                    : ''),
            // Null (not an empty string) when there's nothing to evaluate
            // right now — the client clears the panel in that case.
            'evaluationHtml' => ($data['canEvaluate'] && $data['evaluationSubmission'])
                ? view('room-session.partials.evaluation-panel', $data)->render()
                : null,
        ])->withHeaders($noStore);
    }

    private function buildHomeData(Request $request, RoomSession $roomSession, ?TerminalConnection $connection, RoomQueuePreviewService $roomQueuePreviewService): array
    {
        $roomSession->loadMissing('presentationEvent', 'presentationDateRoom.presentationDate.category.categoryScheduleSetting');
        $room = $roomSession->presentationDateRoom;
        $connectionService = app(TerminalConnectionService::class);

        // Reconciles the room's BREAK status with the clock before anything
        // below reads it — the screen's 5s poll is what keeps it current.
        app(RoomBreakService::class)->sync($roomSession);

        $connection?->loadMissing('panelist.profile', 'roomTerminal');
        $seats = $connectionService->seatSummary($roomSession);

        $queue = $roomQueuePreviewService->forRoom($room);

        // View All (the tablet's "running list") is deliberately not the
        // room's whole-day schedule — user-directed 2026-08-18: a group
        // drops off the list the moment it actually starts presenting, so
        // whoever's next always rises to the top. Called-but-not-yet-
        // started still counts as "waiting" and stays at the front; once
        // ONGOING/PAUSED it's no longer in line, and terminal outcomes
        // (COMPLETED/ABSENT/CANCELLED) are excluded the same way $queue's
        // own 'upcoming' key already is.
        $fullQueue = collect($queue['called'] ? [$queue['called']] : [])->merge($queue['upcoming'] ?? [])->values();

        $canControlFlow = false;
        $canEvaluate = false;
        $assignmentClassification = null;
        $replaceableCandidates = collect();
        $pendingSubstitutionRequest = null;

        if ($connection) {
            $eligibleIds = $connectionService->eligiblePanelistIds($roomSession);
            $canEvaluate = $eligibleIds->contains($connection->panelist_user_id);

            // User-directed 2026-09-07: flow control follows the connected
            // panelist — see TerminalConnectionService::isLead().
            $canControlFlow = $connectionService->isLead($roomSession, $connection->panelist);

            // Drives the loading-beat/toast/replacement-picker on the screen
            // (room-session.partials.connection-panel-connected +
            // substitution-modal) — see TerminalConnectionService::
            // classifyForRoom() and App\Services\PanelSubstitutionService.
            $assignmentClassification = $connectionService->classifyForRoom($roomSession, $connection->panelist);

            if (in_array($assignmentClassification['kind'], ['backup', 'unassigned'], true)) {
                $replaceableCandidates = app(PanelSubstitutionService::class)
                    ->replaceableCandidates($assignmentClassification['attempt'], $roomSession);
            }

            if ($assignmentClassification['kind'] === 'unassigned') {
                $pendingSubstitutionRequest = PanelSubstitutionRequest::where('presentation_attempt_id', $assignmentClassification['attempt']->id)
                    ->where('requested_substitute_user_id', $connection->panelist_user_id)
                    ->whereHas('status', fn ($query) => $query->where('code', 'PENDING'))
                    ->latest('id')
                    ->first();
            }
        }

        // Session/panelist info (session, attempt, run, connectedPanelistIds)
        // is shown on every terminal now — user-directed 2026-08-18:
        // Terminal 2/3 get the same upper info card + "next panelist to
        // call" display Terminal 1 has, just without the actual control
        // buttons. The Current/Last Group status block (attempt-status-card
        // partial) is the same story, extended 2026-08-21: it used to be
        // computed only for the Lead (bundled into buildControlActionsData
        // below), which meant Terminal 2/3 never saw it even in the exact
        // same "nothing called yet, no panel connected" state Terminal 1
        // shows its last-resolved-group info in — user-directed correction,
        // the only thing that should differ between Lead and non-Lead is
        // the control buttons themselves, not this display. So
        // paymentVerification/paymentRequired/lastOutcome move here,
        // computed for every terminal. Only the mutation-decision extras
        // below (hasNextInQueue, allEvaluationsSubmitted,
        // defer reasons) stay Lead-only, since those exist purely to drive
        // buttons Terminal 2/3 never see.
        $info = $this->buildSessionInfoData($roomSession);

        $category = $room->presentationDate->category;
        $category->loadMissing('categoryPaymentSetting', 'categoryPaymentTypes');

        $paymentService = app(PaymentVerificationService::class);
        $info['paymentTypes'] = $paymentService->typesFor($category);
        $info['paymentSummary'] = $info['attempt'] ? $paymentService->summaryFor($category, $info['attempt']) : null;
        $info['paymentRequired'] = $paymentService->isRequired($category);
        $info['lastOutcome'] = $info['attempt'] ? null : $this->buildLastOutcome($room);

        // Break state, shown to every terminal (the warning under the timer, the
        // BREAK banner); the Lead's decision prompt and End Break button ride
        // on the same keys. See RoomBreakService.
        $breakService = app(RoomBreakService::class);
        $info['onBreak'] = $breakService->onBreak($roomSession);
        $info['breakDecision'] = $breakService->pendingDecision($roomSession);
        $info['upcomingBreak'] = $breakService->upcomingBreak(
            $roomSession,
            $info['attempt'],
            $info['run'],
            (int) ($category->categoryScheduleSetting?->duration_minutes ?? 0),
        );

        $control = [];

        if ($canControlFlow) {
            $control = $this->buildControlActionsData($roomSession, $queue['next'] !== null);
        }

        $evaluation = $this->buildEvaluationData($info['attempt'], $connection, $canEvaluate);

        // Both $info's and $control's keys are merged flat into this array —
        // partials/presentation-control.blade.php and
        // partials/terminal-info-card.blade.php read them as bare
        // variables, same as every other key here, rather than nesting them
        // under a key they'd have to unwrap.
        return array_merge([
            'roomSession' => $roomSession,
            'seats' => $seats,
            // Which screen this is and where its forms post — see the class doc.
            'panelistMode' => $this->panelistMode($request),
            'rs' => $this->panelistMode($request) ? self::PANELIST_ROUTES : self::PUBLIC_ROUTES,
            'room' => $room,
            'connection' => $connection,
            'queue' => $queue,
            'fullQueue' => $fullQueue,
            'canControlFlow' => $canControlFlow,
            'canEvaluate' => $canEvaluate,
            'assignmentClassification' => $assignmentClassification,
            'replaceableCandidates' => $replaceableCandidates,
            'pendingSubstitutionRequest' => $pendingSubstitutionRequest,
            'letterhead' => EvaluationLetterhead::forCollege($category->college_id),
            'dayStats' => $this->buildDayStats($room),
            // "Moved/transferred by <admin> — call the next group" — cleared
            // when the Lead calls the next group (PresentationControlService::callNext()).
            'sessionNotices' => $roomSession->notices()->whereNull('acknowledged_at')->orderBy('id')->get(),
        ], $info, $control, $evaluation);
    }

    /**
     * The connected panelist's own EvaluationSubmission for whatever's
     * currently live in this room — created only by
     * PresentationControlService::start()'s EvaluationSubmissionService
     * call, so this stays null until Start has actually been pressed (and,
     * per the guard there, no attempt without an assigned evaluation form
     * ever reaches this point with a submission to find). Also loads the
     * real group data (students, proposed titles) the live-fill paper needs
     * in place of the builder's illustrative placeholders.
     */
    private function buildEvaluationData(?PresentationAttempt $attempt, ?TerminalConnection $connection, bool $canEvaluate): array
    {
        if (! $attempt || ! $connection || ! $canEvaluate) {
            return ['evaluationSubmission' => null];
        }

        $submission = EvaluationSubmission::where('presentation_attempt_id', $attempt->id)
            ->where('panelist_user_id', $connection->panelist_user_id)
            ->with([
                'submissionStatus',
                'evaluationScores',
                'studentScores',
                'evaluationFormVersion' => fn ($query) => $query->with([
                    'evaluationForm',
                    'scaleLabels',
                    'presentationOutcomes',
                    'applicablePresentationModes',
                    'evaluationCriteria' => fn ($criteriaQuery) => $criteriaQuery->orderBy('sort_order'),
                ]),
            ])
            ->first();

        if ($submission) {
            $attempt->loadMissing(
                'researchGroup.students',
                'researchGroup.proposedTitles',
                'attemptPanelAssignments.assignmentStatus',
            );
        }

        return ['evaluationSubmission' => $submission];
    }

    /**
     * User-directed 2026-08-17: registered-in-category is the only one of
     * the three that's deliberately category-wide rather than room/day
     * scoped — the other two describe "this room, today." Deferred groups
     * are excluded on purpose (flagged by the user as a future, panel-login
     * -gated addition, not part of this count) — a reinserted group already
     * satisfies "scheduled today" on its own merit once removed_at clears
     * again, so no special-case is needed for it here.
     */
    private function buildDayStats(PresentationDateRoom $room): array
    {
        $category = $room->presentationDate->category;

        // A group with no slot ("to be scheduled") is not on today's schedule.
        $scheduledToday = AttemptSchedule::where('presentation_date_room_id', $room->id)
            ->whereNotNull('planned_start_at')
            ->whereHas('queueEntry', fn ($query) => $query->whereNull('removed_at'))
            ->count();

        $completedToday = AttemptSchedule::where('presentation_date_room_id', $room->id)
            ->whereHas('presentationAttempt.presentationStatus', fn ($query) => $query->where('code', 'COMPLETED'))
            ->count();

        return [
            'registeredInCategory' => $category->researchGroups()->count(),
            'scheduledToday' => $scheduledToday,
            'completedToday' => $completedToday,
        ];
    }

    /**
     * Session/panelist info every terminal shows now — user-directed
     * 2026-08-18: Terminal 2/3 get the same upper info card and "next
     * panelist to call" display as Terminal 1, just without any control
     * buttons, so this half stays independent of $canControlFlow.
     */
    private function buildSessionInfoData(RoomSession $session): array
    {
        $session->loadMissing(
            'roomSessionStatus',
            'presentationEvent',
            'currentAttempt.presentationStatus',
            'currentAttempt.researchGroup',
            'currentAttempt.presentationRun.timerStatus',
            'currentAttempt.paymentVerifications.paymentStatus',
        );

        // Panelists currently connected to *any* terminal in this room
        // session — used to badge "Active" next to a panelist's name
        // wherever they're listed (current/called group, next-to-call
        // group), independent of which terminal they happen to be sitting
        // at, and independent of which terminal is viewing the page.
        $connectedPanelistIds = TerminalConnection::whereHas(
            'roomTerminal',
            fn ($query) => $query->where('room_session_id', $session->id)
        )
            ->whereNull('disconnected_at')
            ->pluck('panelist_user_id');

        return [
            'session' => $session,
            'attempt' => $session->currentAttempt,
            'run' => $session->currentAttempt?->presentationRun,
            'connectedPanelistIds' => $connectedPanelistIds,
        ];
    }

    /**
     * Everything the Lead's Presentation Control buttons need to decide
     * which are enabled and what they show — resolved once here so
     * partials/presentation-control.blade.php stays pure display logic.
     * Lead-only: Terminal 2/3 never render these buttons at all. (Payment
     * and lastOutcome used to live here too, but 2026-08-21 they moved to
     * buildHomeData() so every terminal — not just the Lead — gets the
     * same Current/Last Group status display; only the actual button-gating
     * data stays here.)
     */
    private function buildControlActionsData(RoomSession $session, bool $nextInQueueExists): array
    {
        $hasNextInQueue = $session->current_attempt_id === null && $nextInQueueExists;
        $attempt = $session->currentAttempt;

        return [
            'hasNextInQueue' => $hasNextInQueue,
            // Gates the Start button — user-directed 2026-08-21: requires an actual panelist connected to every enabled seat,
            // enforced server-side in PresentationControlService::start()
            // and mirrored here just to pre-emptively disable the button.
            'allTerminalsStaffed' => app(PresentationControlService::class)->allTerminalsStaffed($session),
            // Also gates Start — user-directed 2026-09-13: a group whose
            // panel no longer matches its room's panelist_count (changed in
            // Presentation Setup after the panel was assigned) must be
            // reassigned first. Enforced server-side in
            // PresentationControlService::start(); mirrored here to
            // pre-emptively disable the button, same as the two above.
            'panelRequirementIssue' => $attempt
                ? app(PanelAssignmentService::class)->panelRequirementIssue(
                    $attempt->loadMissing(
                        'attemptSchedule.presentationDateRoom',
                        'attemptPanelAssignments.assignmentKind',
                        'attemptPanelAssignments.assignmentStatus',
                    )
                )
                : null,
            // Gates the Complete button — user-directed 2026-08-18: every
            // connected panelist must submit their own evaluation first.
            // True (not blocking) when there's no current attempt at all,
            // since Complete isn't shown in that state anyway.
            'allEvaluationsSubmitted' => ! $attempt || app(EvaluationSubmissionService::class)->allSubmitted($attempt),
            // Gates Defer/Refer-to-Admin — user-directed 2026-09-17: once
            // one of the assigned panel has already scored this group, it
            // can no longer be sent back to the queue (matches the same
            // guard QueueAdjustmentService::defer() now enforces server-
            // side, which both of those actions go through).
            'hasSubmittedEvaluation' => $attempt && app(EvaluationSubmissionService::class)->hasRealSubmission($attempt),
            'adjustmentReasons' => AdjustmentReason::forDefer()->get(),
        ];
    }

    /**
     * Most recently resolved attempt in this room — either a terminal
     * outcome (COMPLETED/ABSENT/CANCELLED, ordered by completed_at) or a
     * DEFERRED one (ordered by its DEFER action's performed_at, since
     * presentation_attempts has no dedicated "deferred_at" column and a
     * deferred attempt's queue_entry.removed_at means it's excluded from
     * RoomQueuePreviewService::forRoom()'s own active-queue-scoped
     * $schedules entirely — this is a separate, room-scoped lookup rather
     * than an extension of that service's lastCompleted).
     */
    private function buildLastOutcome(PresentationDateRoom $room): ?array
    {
        $schedules = AttemptSchedule::where('presentation_date_room_id', $room->id)
            ->whereHas('presentationAttempt.presentationStatus', fn ($query) => $query->whereIn('code', ['COMPLETED', 'ABSENT', 'CANCELLED', 'DEFERRED']))
            ->with([
                'presentationAttempt.presentationStatus',
                'presentationAttempt.researchGroup',
                'presentationAttempt.presentationRun.presentationActions' => fn ($query) => $query
                    ->whereHas('actionType', fn ($subQuery) => $subQuery->whereIn('code', ['COMPLETE', 'DEFER']))
                    ->with(['reason'])
                    ->latest('performed_at'),
            ])
            ->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        $latestSchedule = $schedules->sortByDesc(function ($schedule) {
            $attempt = $schedule->presentationAttempt;
            $action = $attempt->presentationRun?->presentationActions->first();

            return $action?->performed_at ?? $attempt->completed_at ?? $attempt->created_at;
        })->first();

        $attempt = $latestSchedule->presentationAttempt;
        $action = $attempt->presentationRun?->presentationActions->first();
        $isDeferred = $attempt->presentationStatus->code === 'DEFERRED';

        return [
            'attempt' => $attempt,
            'deferReason' => $isDeferred ? $action?->reason : null,
            'deferRemarks' => $isDeferred ? $action?->remarks : null,
            // Actual start/end for a resolved presentation, or the moment
            // it was deferred instead — user-directed 2026-08-21.
            // performed_at is the DEFER action's own timestamp; started_at/
            // completed_at come from the run (a deferred-while-still-just-
            // CALLED group never reached started_at at all, hence the
            // null-safe chain).
            'startedAt' => ! $isDeferred ? $attempt->presentationRun?->started_at : null,
            'completedAt' => ! $isDeferred ? $attempt->presentationRun?->completed_at : null,
            'deferredAt' => $isDeferred ? $action?->performed_at : null,
        ];
    }

    /**
     * The backup/unassigned replacement picker's submit target
     * (room-session.partials.substitution-modal-body). Re-derives the
     * connected panelist and their classification entirely server-side —
     * the client only ever supplies which assigned seat they're requesting
     * to replace.
     */
    public function requestSubstitution(Request $request, TerminalConnectionService $connectionService, PanelSubstitutionService $substitutionService)
    {
        [$roomSession, $connection] = $this->requireRoom($request);

        abort_unless($connection, 404);
        $connection->loadMissing('panelist');

        $validated = $request->validate([
            'original_panelist_id' => ['required', 'integer'],
        ]);

        $classification = $connectionService->classifyForRoom($roomSession, $connection->panelist);

        if (! in_array($classification['kind'], ['backup', 'unassigned'], true) || ! $classification['attempt']) {
            return response()->json(['message' => 'Nothing to request right now.'], 422);
        }

        $result = $substitutionService->request(
            $classification['attempt'],
            $roomSession,
            $connection->panelist,
            (int) $validated['original_panelist_id'],
            $classification['kind'],
            $connection->panelist_user_id
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['status' => $result['status']]);
    }

    /**
     * Presentation Control (functional-spec §5.5/§9.9). Every action below
     * re-derives the connected panelist from this device's session rather
     * than trusting anything client-supplied, and requires that panelist to
     * be the room's designated Lead/Chair (TerminalConnectionService::
     * isLead(), user-directed 2026-09-07). All delegate to
     * PresentationControlService and redirect back to the room screen with a
     * flash message.
     */
    public function callNext(Request $request, PresentationControlService $service)
    {
        return $this->handleControlAction($request, fn ($session, $connection) => $service->callNext($session, $connection), 'Next group called.');
    }

    public function verifyPayment(Request $request, PresentationControlService $service)
    {
        $validated = $request->validate([
            'category_payment_type_id' => ['required', 'integer'],
            // "the field will hold number and letters" — a typical OR/
            // reference number, alphanumeric with the punctuation a real
            // receipt number tends to carry.
            'reference_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
        ]);

        return $this->handleControlAction(
            $request,
            fn ($session, $connection) => $service->verifyPayment($session, $connection, (int) $validated['category_payment_type_id'], $validated['reference_number']),
            'Payment verified.'
        );
    }

    public function cancelBreak(Request $request, RoomBreakService $breaks)
    {
        $breakId = (int) $request->validate(['break_id' => ['required', 'integer']])['break_id'];

        return $this->handleControlAction(
            $request,
            fn ($session, $connection) => $breaks->cancelBreak($session, $breakId, $connection->panelist_user_id),
            'Break cancelled.'
        );
    }

    public function keepBreak(Request $request, RoomBreakService $breaks)
    {
        $breakId = (int) $request->validate(['break_id' => ['required', 'integer']])['break_id'];

        return $this->handleControlAction(
            $request,
            fn ($session, $connection) => $breaks->keepBreak($session, $breakId),
            'The break will start once this group finishes.'
        );
    }

    public function endBreak(Request $request, RoomBreakService $breaks)
    {
        return $this->handleControlAction(
            $request,
            fn ($session, $connection) => $breaks->endBreak($session, $connection->panelist_user_id),
            'Break ended.'
        );
    }

    public function startPresentation(Request $request, PresentationControlService $service)
    {
        return $this->handleControlAction($request, fn ($session, $connection) => $service->start($session, $connection), 'Presentation started.');
    }

    public function pausePresentation(Request $request, PresentationControlService $service)
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']])['remarks'] ?? null;

        return $this->handleControlAction($request, fn ($session, $connection) => $service->pause($session, $connection, $remarks), 'Presentation paused.');
    }

    public function resumePresentation(Request $request, PresentationControlService $service)
    {
        return $this->handleControlAction($request, fn ($session, $connection) => $service->resume($session, $connection), 'Presentation resumed.');
    }

    public function completePresentation(Request $request, PresentationControlService $service)
    {
        return $this->handleControlAction($request, fn ($session, $connection) => $service->complete($session, $connection), 'Presentation marked complete.');
    }

    public function deferPresentation(Request $request, PresentationControlService $service)
    {
        $validated = $request->validate([
            'reason_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('adjustment_reasons', 'id')->where('is_defer_reason', true)],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->handleControlAction(
            $request,
            fn ($session, $connection) => $service->defer($session, $connection, (int) $validated['reason_id'], $validated['remarks'] ?? null),
            'Group deferred.'
        );
    }

    /**
     * The four evaluation actions below are fetch/JSON-driven from
     * evaluation-panel.blade.php rather than plain-POST-then-redirect like
     * every action above — rating buttons and the outcome picker need to
     * feel instant across many rapid clicks per presentation, unlike the
     * once-per-group control actions. Each re-derives the connected
     * panelist's own submission via requireEvaluationSubmission() rather
     * than trusting any client-supplied submission/panelist id — a panelist
     * can only ever touch their own evaluation for whatever's currently
     * live in the room they're physically connected to.
     */
    public function saveEvaluationScore(Request $request, EvaluationSubmissionService $service)
    {
        [, , $submission] = $this->requireEvaluationSubmission($request);

        // Standard-mode rating buttons post an empty string for
        // proposed_title_id (Blade renders `data-proposed-title-id=""` when
        // there's no title) — normalized to null explicitly here rather
        // than relying on ConvertEmptyStringsToNull middleware ordering.
        if ($request->input('proposed_title_id') === '') {
            $request->merge(['proposed_title_id' => null]);
        }

        $validated = $request->validate([
            'evaluation_criterion_id' => ['required', 'integer', 'exists:evaluation_criteria,id'],
            'proposed_title_id' => ['nullable', 'integer', 'exists:proposed_titles,id'],
            'score' => ['required', 'numeric'],
        ]);

        $criterion = EvaluationCriterion::findOrFail($validated['evaluation_criterion_id']);
        $proposedTitle = ! empty($validated['proposed_title_id']) ? ProposedTitle::find($validated['proposed_title_id']) : null;

        $result = $service->saveScore($submission, $criterion, (float) $validated['score'], $proposedTitle);

        return $this->respondEvaluationAction($request, $result, 'Rating saved.');
    }

    public function saveEvaluationStudentScore(Request $request, EvaluationSubmissionService $service)
    {
        [, , $submission] = $this->requireEvaluationSubmission($request);

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $student = Student::findOrFail($validated['student_id']);

        $result = $service->saveStudentScore($submission, $student, isset($validated['score']) ? (float) $validated['score'] : null);

        return $this->respondEvaluationAction($request, $result, 'Individual score saved.');
    }

    public function saveEvaluationOutcome(Request $request, EvaluationSubmissionService $service)
    {
        [, , $submission] = $this->requireEvaluationSubmission($request);

        $validated = $request->validate([
            'presentation_outcome_id' => ['nullable', 'integer', 'exists:presentation_outcomes,id'],
        ]);

        $result = $service->saveOutcome($submission, $validated['presentation_outcome_id'] ?? null);

        // Lets the tablet enable/disable Submit the moment a remark is
        // picked, instead of waiting for the next 5s poll to re-render the
        // panel — the outcome is normally the last thing filled in, so the
        // wait would land exactly when the panelist wants to submit.
        if ($result['ok']) {
            $result['canSubmit'] = $service->countMissingScores($submission->fresh()) === 0
                && ! $service->outcomeMissing($submission->fresh());
        }

        return $this->respondEvaluationAction($request, $result, 'Outcome saved.');
    }

    public function saveEvaluationRemarks(Request $request, EvaluationSubmissionService $service)
    {
        [, , $submission] = $this->requireEvaluationSubmission($request);

        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $service->saveRemarks($submission, $validated['remarks'] ?? null);

        return $this->respondEvaluationAction($request, $result, 'Remarks saved.');
    }

    /**
     * Unlike the three above, submitting stays a plain POST-then-redirect —
     * it happens once per group, not many times a minute, so there's no
     * snappiness need that would justify a fetch call, and a normal redirect
     * re-renders the panel read-only exactly like every other control
     * action already re-renders its own state on redirect.
     */
    public function submitEvaluation(Request $request, EvaluationSubmissionService $service)
    {
        [, , $submission] = $this->requireEvaluationSubmission($request);

        $result = $service->submit($submission);

        return $result['ok']
            ? redirect()->route('panelist.live-room.home')->with('status', 'Evaluation submitted.')
            : redirect()->route('panelist.live-room.home')->with('error', $result['error']);
    }

    private function handleControlAction(Request $request, \Closure $action, string $successMessage)
    {
        [$roomSession, $connection] = $this->requireLeadConnection($request);

        $result = $action($roomSession, $connection);

        return $result['ok']
            ? redirect()->route('panelist.live-room.home')->with('status', $successMessage)
            : redirect()->route('panelist.live-room.home')->with('error', $result['error']);
    }

    private function respondEvaluationAction(Request $request, array $result, string $successMessage)
    {
        if ($result['ok']) {
            $payload = ['message' => $successMessage];

            if (isset($result['totals'])) {
                $payload['totals'] = $result['totals'];
            }

            if (isset($result['canSubmit'])) {
                $payload['canSubmit'] = $result['canSubmit'];
            }

            return $request->wantsJson()
                ? response()->json($payload)
                : redirect()->route('panelist.live-room.home')->with('status', $successMessage);
        }

        return $request->wantsJson()
            ? response()->json(['message' => $result['error']], 422)
            : redirect()->route('panelist.live-room.home')->with('error', $result['error']);
    }

    /**
     * The security boundary for all four evaluation actions: re-derives the
     * connected panelist and their submission entirely from this device's own
     * session, never from anything the client posts. 404s (not 403)
     * throughout, so an ineligible or empty seat looks the same as "there's
     * nothing here" rather than confirming a submission exists.
     */
    private function requireEvaluationSubmission(Request $request): array
    {
        [$roomSession, $connection] = $this->requireRoom($request);

        abort_unless($connection, 404);

        $roomSession->loadMissing('currentAttempt');
        $attempt = $roomSession->currentAttempt;

        abort_unless($attempt, 404);

        $eligibleIds = app(TerminalConnectionService::class)->eligiblePanelistIds($roomSession);
        abort_unless($eligibleIds->contains($connection->panelist_user_id), 404);

        $submission = EvaluationSubmission::where('presentation_attempt_id', $attempt->id)
            ->where('panelist_user_id', $connection->panelist_user_id)
            ->first();

        abort_unless($submission, 404);

        return [$roomSession, $connection, $submission];
    }

    /**
     * Presentation Control needs a panelist signed in on this device, and that
     * panelist must be the room's designated Lead/Chair
     * (attempt_panel_assignments.is_lead, TerminalConnectionService::isLead()).
     */
    private function requireLeadConnection(Request $request): array
    {
        [$roomSession, $connection] = $this->requireRoom($request);

        abort_unless($connection, 403);
        $connection->loadMissing('panelist');
        abort_unless(app(TerminalConnectionService::class)->isLead($roomSession, $connection->panelist), 403);

        return [$roomSession, $connection];
    }

    private function panelistMode(Request $request): bool
    {
        return $request->routeIs(self::PANELIST_ROUTES . '*');
    }

    /**
     * Every room session that is started and not yet closed, by category then
     * room name.
     */
    private function openRoomSessions(): Collection
    {
        return RoomSession::open()
            ->with('presentationDateRoom.presentationDate.category')
            ->get()
            ->filter(fn ($session) => $session->presentationDateRoom?->presentationDate?->category)
            ->sortBy(fn ($session) => sprintf('%s|%s',
                $session->presentationDateRoom->presentationDate->category->name,
                $session->presentationDateRoom->room_name
            ))
            ->values();
    }

    /**
     * The room the public display shows, while it is still running. A room
     * that was ended (or a stale id) clears the device back to the list.
     */
    private function watchedRoomSession(Request $request): ?RoomSession
    {
        $id = $request->session()->get('room_view.room_session_id');

        if (! $id) {
            return null;
        }

        $roomSession = RoomSession::open()->find($id);

        if (! $roomSession) {
            $request->session()->forget('room_view.room_session_id');
        }

        return $roomSession;
    }

    /**
     * The panelist's open seat in a running room, if any — their current room.
     */
    private function panelistConnection(User $panelist): ?TerminalConnection
    {
        return TerminalConnection::where('panelist_user_id', $panelist->id)
            ->whereNull('disconnected_at')
            ->whereHas('roomTerminal.roomSession', fn ($query) => $query->open())
            ->with('roomTerminal.roomSession')
            ->latest('id')
            ->first();
    }

    /**
     * [RoomSession, ?TerminalConnection] for this request, or a redirect to the
     * matching room list. Panelist mode needs a seat; the public display never
     * has one.
     */
    private function requireRoom(Request $request): array
    {
        if ($this->panelistMode($request)) {
            $connection = $this->panelistConnection($request->user());

            if (! $connection) {
                throw new HttpResponseException(
                    redirect()->route('panelist.live-room.index')->with('error', 'You are not in a room. Join one first.')
                );
            }

            return [$connection->roomTerminal->roomSession, $connection];
        }

        $roomSession = $this->watchedRoomSession($request);

        if (! $roomSession) {
            throw new HttpResponseException(
                redirect()->route('room-session.entry')->with('error', 'This room session has ended.')
            );
        }

        return [$roomSession, null];
    }

    private function disconnect(TerminalConnection $connection): void
    {
        $connection->update([
            'disconnected_at' => now(),
            'connection_status_id' => ConnectionStatus::where('code', 'DISCONNECTED')->firstOrFail()->id,
        ]);
    }
}
