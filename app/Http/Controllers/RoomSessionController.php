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
use App\Models\RoomSessionAccount;
use App\Models\RoomTerminal;
use App\Models\Student;
use App\Models\TerminalAccessToken;
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
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Public, unauthenticated flow for the physical tablet placed in a room
 * (user-directed 2026-08-15 correction of an earlier version of this flow
 * that conflated the room account with a per-panelist login — it isn't;
 * see below). Two distinct layers, both against tables that already existed
 * in the schema unused before this feature:
 *
 *  1. Device claim (this controller, once per tablet per day): an Admin
 *     opens this on the physical tablet, signs in with the room's shared
 *     account, and picks which terminal number (1/2/3) that specific
 *     device is. This binds room_terminals.device_identifier and is stored
 *     in that browser's session — a room_session_accounts row is a
 *     device-setup credential, never typed by a panelist.
 *  2. Panelist identification (per terminal, per panelist, many times a
 *     day): once a tablet is claimed, its "home" screen shows a QR code —
 *     scanned with the panelist's own already-logged-in phone, handled by
 *     Panelist\TerminalScanController — plus a manual username/password
 *     fallback using the panelist's own personal account, both funneling
 *     through TerminalConnectionService so the eligibility/conflict rules
 *     live in one place.
 */
class RoomSessionController extends Controller
{
    public function login(Request $request)
    {
        if ($request->session()->has('room_login.room_terminal_id')) {
            return redirect()->route('room-session.home');
        }

        if ($request->session()->has('room_login.account_id')) {
            return redirect()->route('room-session.terminal');
        }

        return view('room-session.login');
    }

    public function authenticate(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $account = RoomSessionAccount::where('username', $validated['username'])
            ->where('is_active', true)
            ->first();

        if (! $account || ! hash_equals($account->password, $validated['password'])) {
            return back()->withErrors(['username' => 'Invalid room account username or password.'])->withInput();
        }

        $request->session()->put('room_login.account_id', $account->id);

        return redirect()->route('room-session.terminal');
    }

    /**
     * Terminal-number picker — the device-claim step. Terminal rows
     * (terminal_number 1/2/3...) are already created at Start Event by
     * EventActivationService; this only decides which physical tablet
     * corresponds to which existing row — user-directed 2026-09-07: no
     * terminal number carries any special "Lead" meaning anymore, flow
     * control now follows whichever panelist is designated Lead/Chair in
     * Group & Panel Assignment, wherever they log in (see
     * TerminalConnectionService::isLead()). Already-claimed numbers are
     * still shown (not hidden), so an
     * Admin/panelist can see at a glance which numbers are taken, but
     * claimTerminal() below refuses to let a second device actually take
     * one over — user-corrected 2026-08-22: this used to silently let a
     * re-claim overwrite the existing device_identifier (meant to help a
     * tablet that lost its session recover its own number), but that same
     * door let any *other* tablet steal an already-set-up terminal out from
     * under the device using it. Recovering a genuinely lost/abandoned
     * terminal now goes through an Admin's "Release Device" action in Live
     * Monitoring (Admin\LiveMonitoringController::releaseTerminalDevice())
     * instead of an implicit re-claim.
     */
    public function terminalPicker(Request $request)
    {
        $account = $this->requireAccount($request);

        $roomSession = $this->currentRoomSession($account);

        if (! $roomSession) {
            return view('room-session.waiting', compact('account'));
        }

        $terminals = $roomSession->roomTerminals()->orderBy('terminal_number')->get();

        return view('room-session.terminal-picker', compact('account', 'roomSession', 'terminals'));
    }

    public function claimTerminal(Request $request)
    {
        $account = $this->requireAccount($request);

        $roomSession = $this->currentRoomSession($account);
        abort_unless($roomSession, 404);

        $validated = $request->validate([
            'room_terminal_id' => ['required', 'integer'],
        ]);

        $terminal = $roomSession->roomTerminals->firstWhere('id', (int) $validated['room_terminal_id']);
        abort_unless($terminal, 404);

        if ($terminal->device_identifier) {
            return back()->with('error', "Terminal {$terminal->terminal_number} is already set up on another device — ask an Administrator to release it first.");
        }

        $terminal->update(['device_identifier' => (string) Str::uuid()]);

        $request->session()->forget('room_login.account_id');
        $request->session()->put('room_login.room_terminal_id', $terminal->id);

        return redirect()->route('room-session.home');
    }

    public function home(Request $request, RoomQueuePreviewService $roomQueuePreviewService)
    {
        $terminal = $this->requireTerminal($request);
        $data = $this->buildHomeData($request, $terminal, $roomQueuePreviewService);

        return view('room-session.home', $data);
    }

    /**
     * JSON poller behind the home screen's live-refresh (user-directed
     * 2026-08-16: the earlier full-page window.location.reload() every 5s
     * was "felt" by the user — visible flash, and had to be specially
     * guarded against wiping the manual-login form mid-type. This returns
     * the same data as home(), pre-rendered into the same two partials the
     * page itself uses, so the client only ever does a plain DOM swap —
     * no rendering logic duplicated in JS.
     */
    public function status(Request $request, RoomQueuePreviewService $roomQueuePreviewService)
    {
        $terminal = $this->requireTerminal($request);
        $data = $this->buildHomeData($request, $terminal, $roomQueuePreviewService);

        return response()->json([
            // no-store below: this endpoint is polled every 5s specifically
            // to reflect changes another user just made (a panel
            // substitution, a call/complete action) — a cached response
            // (browser heuristics, or a tablet's carrier/wifi proxy) would
            // silently defeat the whole point of polling. Explicit on every
            // response rather than global middleware since nothing else in
            // this app polls this aggressively.
            // The client reloads the whole page the instant this flips
            // (see home.blade.php's poll()) rather than DOM-swapping —
            // #control-panel's parent element differs between the
            // connected (merged into the left sidebar) and unclaimed
            // (merged into the right card) layouts, 2026-08-21.
            'connected' => (bool) $data['connection'],
            // Same reload-on-change treatment, 2026-08-22: an Admin
            // approving a pending "unassigned" request flips this from
            // 'unassigned' to 'assigned' without the connection itself ever
            // dropping, so `connected` alone wouldn't notice it — see
            // home.blade.php's poll().
            'classification' => $data['assignmentClassification']['kind'] ?? null,
            // qrSvg refreshes independently — the QR's access token still
            // needs to rotate before it expires even while nobody's
            // connected yet.
            'qrSvg' => $data['qrSvg'],
            // Session info (stats + panelist strip) is shown to every
            // terminal in the right column now (2026-08-18 wireframe) — no
            // Lead/non-Lead split, unlike controlHtml below.
            'sessionInfoHtml' => view('room-session.partials.control-info-card', $data)->render(),
            // The "Next" preview used to be the tail end of
            // control-info-card.blade.php; it's now a separate fragment so
            // it can render after the (Lead-only) control buttons in DOM
            // order — see home.blade.php's merged "Presentation Control"
            // card, 2026-08-21.
            'sessionInfoNextHtml' => view('room-session.partials.control-info-next', $data)->render(),
            'queueHtml' => view('room-session.partials.queue-panel', $data)->render(),
            // #control-panel's content — the Current/Last Group status
            // block for every terminal, plus the control buttons for the
            // Lead only. This is the one thing that switches which column
            // it's mounted in as $connection flips (left sidebar once
            // connected, right card while unclaimed — see home.blade.php's
            // layout comment), but the content itself is identical either
            // way, so it's built the same here regardless of connection
            // state. showHeader must match whichever layout is currently
            // rendered (connected -> left sidebar, own "Presentation
            // Controls" title; unclaimed -> right card, no title of its own
            // since control-info-card's header already covers it) or the
            // title would vanish on the very next poll tick.
            'controlHtml' => view('room-session.partials.attempt-status-card', $data)->render()
                . ($data['canControlFlow']
                    ? view('room-session.partials.presentation-control', $data + ['showHeader' => (bool) $data['connection']])->render()
                    : ''),
            // Null (not an empty string) when there's nothing to evaluate
            // right now (no current attempt, not eligible, or Start hasn't
            // been pressed yet) — the client clears the panel in that case
            // rather than swapping in empty markup.
            'evaluationHtml' => ($data['canEvaluate'] && $data['evaluationSubmission'])
                ? view('room-session.partials.evaluation-panel', $data)->render()
                : null,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildHomeData(Request $request, RoomTerminal $terminal, RoomQueuePreviewService $roomQueuePreviewService): array
    {
        $terminal->loadMissing('roomSession.presentationEvent', 'roomSession.presentationDateRoom.presentationDate.category.categoryScheduleSetting');
        $room = $terminal->roomSession->presentationDateRoom;

        // Reconciles the room's BREAK status with the clock before anything
        // below reads it — the tablet's 5s poll is what keeps it current.
        app(RoomBreakService::class)->sync($terminal->roomSession);

        $connection = TerminalConnection::where('room_terminal_id', $terminal->id)
            ->whereNull('disconnected_at')
            ->with('panelist.profile')
            ->latest('id')
            ->first();

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

        $qrSvg = null;

        if (! $connection) {
            $qrUrl = route('panelist.terminal-scan.show', $this->currentAccessToken($request, $terminal));
            $qrSvg = $this->renderQrSvg($qrUrl);
        }

        $canControlFlow = false;
        $canEvaluate = false;
        $assignmentClassification = null;
        $replaceableCandidates = collect();
        $pendingSubstitutionRequest = null;

        if ($connection) {
            $connectionService = app(TerminalConnectionService::class);
            $eligibleIds = $connectionService->eligiblePanelistIds($terminal->roomSession);
            $canEvaluate = $eligibleIds->contains($connection->panelist_user_id);

            // User-directed 2026-09-07: flow control now follows the
            // connected panelist, not a fixed Terminal 1/Lead seat — see
            // TerminalConnectionService::isLead().
            $canControlFlow = $connectionService->isLead($terminal->roomSession, $connection->panelist);

            // Drives the loading-beat/toast/replacement-picker on the tablet
            // (room-session.partials.connection-panel-connected +
            // substitution-modal) — see TerminalConnectionService::
            // classifyForRoom() and App\Services\PanelSubstitutionService.
            $assignmentClassification = $connectionService->classifyForRoom($terminal->roomSession, $connection->panelist);

            if (in_array($assignmentClassification['kind'], ['backup', 'unassigned'], true)) {
                $replaceableCandidates = app(PanelSubstitutionService::class)
                    ->replaceableCandidates($assignmentClassification['attempt'], $terminal->roomSession);
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
        // below (hasNextInQueue, allTerminalsActive, allEvaluationsSubmitted,
        // defer reasons) stay Lead-only, since those exist purely to drive
        // buttons Terminal 2/3 never see.
        $info = $this->buildSessionInfoData($terminal->roomSession);

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
        $info['onBreak'] = $breakService->onBreak($terminal->roomSession);
        $info['breakDecision'] = $breakService->pendingDecision($terminal->roomSession);
        $info['upcomingBreak'] = $breakService->upcomingBreak(
            $terminal->roomSession,
            $info['attempt'],
            $info['run'],
            (int) ($category->categoryScheduleSetting?->duration_minutes ?? 0),
        );

        $control = [];

        if ($canControlFlow) {
            $control = $this->buildControlActionsData($terminal->roomSession, $queue['next'] !== null);
        }

        $evaluation = $this->buildEvaluationData($info['attempt'], $connection, $canEvaluate);

        // Both $info's and $control's keys are merged flat into this array —
        // partials/presentation-control.blade.php and
        // partials/terminal-info-card.blade.php read them as bare
        // variables, same as every other key here, rather than nesting them
        // under a key they'd have to unwrap.
        return array_merge([
            'terminal' => $terminal,
            'room' => $room,
            'connection' => $connection,
            'queue' => $queue,
            'fullQueue' => $fullQueue,
            'qrSvg' => $qrSvg,
            'canControlFlow' => $canControlFlow,
            'canEvaluate' => $canEvaluate,
            'assignmentClassification' => $assignmentClassification,
            'replaceableCandidates' => $replaceableCandidates,
            'pendingSubstitutionRequest' => $pendingSubstitutionRequest,
            'letterhead' => EvaluationLetterhead::forCollege($category->college_id),
            'dayStats' => $this->buildDayStats($room),
            // "Moved/transferred by <admin> — call the next group" — cleared
            // when the Lead calls the next group (PresentationControlService::callNext()).
            'sessionNotices' => $terminal->roomSession->notices()->whereNull('acknowledged_at')->orderBy('id')->get(),
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
            'allTerminalsActive' => app(PresentationControlService::class)->allTerminalsActive($session),
            // Gates the Start button — user-directed 2026-08-21: distinct
            // from allTerminalsActive() above (device-claimed only) — this
            // requires an actual panelist connected to every enabled seat,
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
            'adjustmentReasons' => AdjustmentReason::where('is_active', true)->orderBy('name')->get(),
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

    public function manualLogin(Request $request, TerminalConnectionService $service)
    {
        $terminal = $this->requireTerminal($request);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $panelist = User::where('username', $validated['username'])
            ->whereHas('userRoles.role', fn ($query) => $query->where('code', 'PANELIST'))
            ->first();

        if (! $panelist || ! Hash::check($validated['password'], $panelist->password)) {
            return back()->with('error', 'Invalid panelist username or password.');
        }

        $outcome = $service->connect($terminal, $panelist, 'MANUAL_CODE', $request->ip(), $request->userAgent());

        if (! $outcome['ok']) {
            return back()->with('error', $outcome['error']);
        }

        return redirect()->route('room-session.home');
    }

    /**
     * The backup/unassigned replacement picker's submit target
     * (room-session.partials.substitution-modal-body). Re-derives the
     * connected panelist, terminal, and their classification entirely
     * server-side — the client only ever supplies which assigned seat
     * they're requesting to replace.
     */
    public function requestSubstitution(Request $request, TerminalConnectionService $connectionService, PanelSubstitutionService $substitutionService)
    {
        $terminal = $this->requireTerminal($request);
        $terminal->loadMissing('roomSession');

        $connection = TerminalConnection::where('room_terminal_id', $terminal->id)
            ->whereNull('disconnected_at')
            ->with('panelist')
            ->latest('id')
            ->first();

        abort_unless($connection, 404);

        $validated = $request->validate([
            'original_panelist_id' => ['required', 'integer'],
        ]);

        $classification = $connectionService->classifyForRoom($terminal->roomSession, $connection->panelist);

        if (! in_array($classification['kind'], ['backup', 'unassigned'], true) || ! $classification['attempt']) {
            return response()->json(['message' => 'Nothing to request right now.'], 422);
        }

        $result = $substitutionService->request(
            $classification['attempt'],
            $terminal->roomSession,
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

    public function logoutPanelist(Request $request)
    {
        $terminal = $this->requireTerminal($request);

        TerminalConnection::where('room_terminal_id', $terminal->id)
            ->whereNull('disconnected_at')
            ->update([
                'disconnected_at' => now(),
                'connection_status_id' => ConnectionStatus::where('code', 'DISCONNECTED')->firstOrFail()->id,
            ]);

        return redirect()->route('room-session.home');
    }

    /**
     * Presentation Control (functional-spec §5.5/§9.9) — "available only to
     * the panelist occupying Terminal 1." Every action below re-derives the
     * terminal claim from the request itself rather than trusting anything
     * client-supplied, since the tablet session only proves device claim,
     * not who's presently sitting at it. All seven delegate to
     * PresentationControlService and redirect back to the home screen with
     * a flash message — the 5s poll (and the redirect's own fresh render)
     * is what shows the result, matching this controller's existing
     * plain-POST-then-redirect convention.
     *
     * User-directed 2026-09-07: Terminal 1 is no longer a fixed Lead seat —
     * the Admin now explicitly designates a Lead/Chair panelist when
     * assigning the panel (attempt_panel_assignments.is_lead), and that
     * panelist gets flow control at whichever terminal they actually log
     * into (TerminalConnectionService::isLead()). Every control action
     * below — including Call Next, which used to be allowed from an empty
     * Terminal 1 purely on its device claim — now requires the designated
     * Lead to actually be connected to the terminal making the request,
     * since there's no longer a fixed seat to trust independently of who's
     * logged in.
     *
     * Same day: End Room Session (the tablet-side manual per-room close)
     * was removed at the user's request — a room session is now only ever
     * closed via the Admin's End Event action
     * (EventActivationService::end(), Live Monitoring), not from the
     * tablet.
     */
    public function callNext(Request $request, PresentationControlService $service)
    {
        $terminal = $this->requireTerminal($request);
        $connection = $this->requireLeadConnection($request, $terminal);

        $result = $service->callNext($terminal->roomSession, $connection);

        return $result['ok']
            ? redirect()->route('room-session.home')->with('status', 'Next group called.')
            : redirect()->route('room-session.home')->with('error', $result['error']);
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
            'reason_id' => ['required', 'integer', 'exists:adjustment_reasons,id'],
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
            ? redirect()->route('room-session.home')->with('status', 'Evaluation submitted.')
            : redirect()->route('room-session.home')->with('error', $result['error']);
    }

    private function handleControlAction(Request $request, \Closure $action, string $successMessage)
    {
        $terminal = $this->requireTerminal($request);
        $connection = $this->requireLeadConnection($request, $terminal);

        $result = $action($terminal->roomSession, $connection);

        return $result['ok']
            ? redirect()->route('room-session.home')->with('status', $successMessage)
            : redirect()->route('room-session.home')->with('error', $result['error']);
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
                : redirect()->route('room-session.home')->with('status', $successMessage);
        }

        return $request->wantsJson()
            ? response()->json(['message' => $result['error']], 422)
            : redirect()->route('room-session.home')->with('error', $result['error']);
    }

    /**
     * The security boundary for all four evaluation actions: re-derives the
     * connected panelist and their submission entirely from the tablet's own
     * session/terminal claim, never from anything the client posts. 404s
     * (not 403) throughout — an ineligible or empty seat looks the same as
     * "there's nothing here" rather than confirming a submission exists.
     */
    private function requireEvaluationSubmission(Request $request): array
    {
        $terminal = $this->requireTerminal($request);

        $connection = TerminalConnection::where('room_terminal_id', $terminal->id)
            ->whereNull('disconnected_at')
            ->latest('id')
            ->first();

        abort_unless($connection, 404);

        $terminal->loadMissing('roomSession.currentAttempt');
        $attempt = $terminal->roomSession->currentAttempt;

        abort_unless($attempt, 404);

        $eligibleIds = app(TerminalConnectionService::class)->eligiblePanelistIds($terminal->roomSession);
        abort_unless($eligibleIds->contains($connection->panelist_user_id), 404);

        $submission = EvaluationSubmission::where('presentation_attempt_id', $attempt->id)
            ->where('panelist_user_id', $connection->panelist_user_id)
            ->first();

        abort_unless($submission, 404);

        return [$terminal, $connection, $submission];
    }

    /**
     * Presentation Control is gated on two things a session cookie alone
     * doesn't prove: someone must currently be connected to this terminal,
     * and that connected panelist must be the room's designated Lead/Chair
     * (attempt_panel_assignments.is_lead — see TerminalConnectionService::
     * isLead()) — user-directed 2026-09-07, replacing the old fixed
     * "Terminal 1/Lead" seat check. An empty seat has no panelist identity
     * to attribute actions to, and a connected non-Lead panelist has no
     * flow-control rights regardless of which terminal number they're on.
     */
    private function requireLeadConnection(Request $request, RoomTerminal $terminal): TerminalConnection
    {
        $connection = TerminalConnection::where('room_terminal_id', $terminal->id)
            ->whereNull('disconnected_at')
            ->with('panelist')
            ->latest('id')
            ->first();

        abort_unless($connection, 403);
        abort_unless(app(TerminalConnectionService::class)->isLead($terminal->roomSession, $connection->panelist), 403);

        return $connection;
    }

    /**
     * Fully releases this device — clears the terminal claim, back to the
     * very first screen. For reassigning a tablet or resetting a stuck one.
     * Also clears device_identifier on the terminal itself, not just the
     * session — otherwise the terminal stays permanently marked "Already
     * set up on another device" on the picker screen for every future
     * claim attempt, since nothing else ever nulls that column back out.
     */
    public function release(Request $request)
    {
        $terminalId = $request->session()->get('room_login.room_terminal_id');

        if ($terminalId) {
            RoomTerminal::where('id', $terminalId)->update(['device_identifier' => null]);
        }

        $request->session()->forget(['room_login.room_terminal_id', 'room_login.account_id']);

        return redirect()->route('room-session.entry');
    }

    public function cancel(Request $request)
    {
        $request->session()->forget('room_login.account_id');

        return redirect()->route('room-session.entry');
    }

    private function requireAccount(Request $request): RoomSessionAccount
    {
        $accountId = $request->session()->get('room_login.account_id');
        abort_unless($accountId, 403);

        $account = RoomSessionAccount::where('id', $accountId)->where('is_active', true)->first();

        if (! $account) {
            $request->session()->forget('room_login.account_id');
            abort(403);
        }

        return $account;
    }

    private function requireTerminal(Request $request): RoomTerminal
    {
        $terminalId = $request->session()->get('room_login.room_terminal_id');
        abort_unless($terminalId, 403);

        $terminal = RoomTerminal::find($terminalId);

        if (! $terminal) {
            $request->session()->forget('room_login.room_terminal_id');
            abort(403);
        }

        return $terminal;
    }

    /**
     * A session is "live" because its room_session_status says so, not
     * because its presentation_date happens to equal today's real calendar
     * date — an Admin can legitimately start a date late (or, in this dev
     * environment, start a backdated test date), and the tablet still needs
     * to find that live session. Previously scoped with
     * whereDate('presentation_date', today()), which meant a genuinely
     * ACTIVE session on any date other than today silently never matched
     * here, even though Live Monitoring correctly showed it as ACTIVE.
     *
     * User-directed 2026-09-07: now scoped through the account's own
     * presentation_date_id rather than category_id + "latest non-closed" —
     * an account is generated fresh per day, so this account can only ever
     * belong to one specific day's session, removing any ambiguity from
     * two different dates in the same category both having a same-named
     * room live at once (e.g. an extended day overlapping the next day's
     * Start Event).
     */
    private function currentRoomSession(RoomSessionAccount $account): ?RoomSession
    {
        return RoomSession::whereHas('presentationDateRoom', function ($query) use ($account) {
            $query->where('room_name', $account->room_name)
                ->where('presentation_date_id', $account->presentation_date_id);
        })
            ->whereHas('roomSessionStatus', fn ($query) => $query->where('code', '!=', 'CLOSED'))
            ->with('roomTerminals.terminalType')
            ->latest('id')
            ->first();
    }

    private function issueAccessToken(RoomTerminal $terminal): string
    {
        $rawToken = Str::random(48);

        TerminalAccessToken::create([
            'room_terminal_id' => $terminal->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(3),
        ]);

        return $rawToken;
    }

    /**
     * The QR page auto-reloads every 5s to notice a claim (see home.blade.php),
     * but home() used to call issueAccessToken() unconditionally on every one
     * of those reloads — minting a brand-new token, and therefore a visibly
     * different QR code, every 5 seconds even though the previous one hadn't
     * expired or been used. This reuses whatever token is already sitting in
     * this tablet's session as long as it's still valid, only minting a fresh
     * one on first load or once the old one genuinely expires. The raw token
     * itself is never persisted anywhere retrievable except here in session —
     * matches the same "never stored in the DB, only its hash" rule the
     * original issueAccessToken() already followed.
     */
    private function currentAccessToken(Request $request, RoomTerminal $terminal): string
    {
        $stored = $request->session()->get('room_login.qr_token');

        if ($stored && $stored['terminal_id'] === $terminal->id && now()->lt($stored['expires_at'])) {
            // Time-valid isn't enough on its own — a panelist can scan and
            // then log out again inside the same window, which marks the
            // underlying row used_at without touching this session cache.
            // Re-displaying an already-used token's QR would look fine but
            // fail the moment anyone actually scanned it.
            $stillUsable = TerminalAccessToken::where('token_hash', hash('sha256', $stored['raw']))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->exists();

            if ($stillUsable) {
                return $stored['raw'];
            }
        }

        $rawToken = $this->issueAccessToken($terminal);

        $request->session()->put('room_login.qr_token', [
            'raw' => $rawToken,
            'terminal_id' => $terminal->id,
            // A little short of the token's real 3-minute expiry so a reload
            // never hands out a QR that's about to go stale mid-scan.
            'expires_at' => now()->addMinutes(3)->subSeconds(15),
        ]);

        return $rawToken;
    }

    /**
     * Rendered server-side (no CDN/JS dependency) so the join QR still
     * works on a tablet with no internet access — the venue's local
     * network to reach this Laravel server is the only connectivity this
     * needs, same as loading the rest of the page. SVG rather than
     * PngWriter so it needs neither GD nor Imagick and embeds inline as
     * plain markup, no separate image request at all.
     */
    private function renderQrSvg(string $data): string
    {
        $result = (new Builder(writer: new SvgWriter(), data: $data, size: 150, margin: 6))->build();

        return $result->getString();
    }
}
