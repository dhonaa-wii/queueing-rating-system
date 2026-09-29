<?php

namespace App\Services;

use App\Exceptions\DuplicatePaymentReferenceException;
use App\Models\AttemptPanelAssignment;
use App\Models\AttemptPanelParticipation;
use App\Models\CategoryPaymentType;
use App\Models\PresentationAction;
use App\Models\PresentationActionType;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\PresentationRun;
use App\Models\PresentationStatus;
use App\Models\RoomSession;
use App\Models\RoomSessionNotice;
use App\Models\RoomSessionStatus;
use App\Models\TerminalConnection;
use App\Models\TerminalType;
use App\Models\TimerStatus;
use Illuminate\Support\Facades\DB;

/**
 * Lead/Chair live control (functional-spec §5.5/§9.9: Call next,
 * Verify payment, Start, Pause, Resume, Complete, Defer —
 * "available only to the panelist occupying Terminal 1"). As of 2026-09-07
 * this is no longer literally Terminal 1 — the Admin explicitly designates
 * a Lead/Chair panelist when assigning the panel
 * (attempt_panel_assignments.is_lead), and that panelist gets control
 * wherever they log in (RoomSessionController::requireLeadConnection(),
 * TerminalConnectionService::isLead()); the service methods here are
 * unchanged either way — they only ever receive a TerminalConnection
 * already proven to belong to the Lead. End Room Session (the eighth
 * action functional-spec §5.5 originally listed) was removed the same day
 * at the user's request — a room session is now only ever closed via the
 * Admin's End Event action (EventActivationService::end()). First writer
 * for
 * presentation_runs/presentation_pauses/presentation_actions/
 * attempt_panel_participations (confirmed greenfield per CLAUDE.md §2.8) and
 * the Lead-initiated half of payment_verifications (the admin-side "resolve
 * a referred concern" half already exists in PaymentVerificationService).
 *
 * Every action pivots on RoomSession::current_attempt_id — there is at most
 * one attempt "in front of" a room at a time, matching how a physical Lead
 * terminal actually works. presentation_runs.presentation_attempt_id is a
 * unique FK (one run row per attempt, ever), so a deferred-then-recalled
 * attempt reuses and resets its existing run row rather than creating a
 * second one; its call/start/pause history up to the defer point is
 * overwritten, which is an accepted simplification — the row models "this
 * attempt's current live cycle", not a full audit trail (that's what
 * presentation_actions is for, and it is never overwritten).
 */
class PresentationControlService
{
    public function __construct(
        private readonly RoomQueuePreviewService $roomQueuePreviewService,
        private readonly QueueAdjustmentService $queueAdjustmentService,
        private readonly EvaluationSubmissionService $evaluationSubmissionService,
        private readonly PanelAssignmentService $panelAssignmentService,
        private readonly PaymentVerificationService $paymentVerificationService,
        private readonly RoomBreakService $roomBreakService,
    ) {
    }

    /**
     * Does not require every terminal to be staffed by a panelist —
     * calling next only marks the group CALLED, it doesn't start the
     * presentation, so an empty evaluation seat isn't a blocker here. That
     * concern still applies once a presentation actually begins, which is
     * what start() (and its own payment-verification gate) is for.
     *
     * User-directed 2026-08-18: it DOES require every enabled terminal in
     * the room to be device-claimed (see allTerminalsActive()) — the
     * physical tablets must be set up before groups start getting called,
     * even though nobody has to be logged into them yet.
     */
    public function callNext(RoomSession $session, ?TerminalConnection $connection): array
    {
        $session->loadMissing('roomSessionStatus', 'presentationDateRoom.presentationDate.category.categoryScheduleSetting', 'presentationDateRoom.presentationDate.category.categoryQueueSetting');

        if ($blocked = $this->blockedBySessionStatus($session)) {
            return $this->failure($blocked);
        }

        if ($breakBlock = $this->blockedByBreak($session)) {
            return $this->failure($breakBlock);
        }

        if ($session->current_attempt_id !== null) {
            return $this->failure('A group is already active in this room — complete or defer it first.');
        }

        if (! $this->allTerminalsActive($session)) {
            return $this->failure('All terminals in this room must be connected before calling the next group.');
        }

        $room = $session->presentationDateRoom;
        $next = $this->roomQueuePreviewService->forRoom($room)['next'];

        if (! $next) {
            return $this->failure('No groups are waiting in this room queue.');
        }

        $category = $room->presentationDate->category;
        $durationSeconds = (int) ($category->categoryScheduleSetting?->duration_minutes ?? 0) * 60;

        if ($durationSeconds <= 0) {
            return $this->failure('This category has no configured presentation duration.');
        }

        $waitingMinutes = (int) ($category->categoryQueueSetting?->called_waiting_minutes ?? 5);
        $attempt = $next->presentationAttempt;
        $now = now();

        return DB::transaction(function () use ($session, $attempt, $durationSeconds, $waitingMinutes, $connection, $now) {
            $run = PresentationRun::firstOrNew(['presentation_attempt_id' => $attempt->id]);
            $run->fill([
                'room_session_id' => $session->id,
                'called_at' => $now,
                'waiting_deadline_at' => $now->copy()->addMinutes($waitingMinutes),
                'started_at' => null,
                'completed_at' => null,
                'configured_duration_seconds' => $durationSeconds,
                'actual_duration_seconds' => null,
                'total_paused_seconds' => 0,
                'extended_seconds' => 0,
                'timer_status_id' => TimerStatus::where('code', 'NOT_STARTED')->firstOrFail()->id,
                'last_action_at' => $now,
            ])->save();

            $attempt->update(['presentation_status_id' => PresentationStatus::where('code', 'CALLED')->firstOrFail()->id]);

            $session->update([
                'current_attempt_id' => $attempt->id,
                'room_session_status_id' => RoomSessionStatus::where('code', 'ACTIVE')->firstOrFail()->id,
                'started_at' => $session->started_at ?? $now,
            ]);

            // Calling the next group answers any "moved/transferred by admin —
            // call the next group" notice the room was showing.
            RoomSessionNotice::where('room_session_id', $session->id)
                ->whereNull('acknowledged_at')
                ->update(['acknowledged_at' => $now]);

            $this->logAction($run, 'CALL', $connection, null, null, $now);

            return ['ok' => true];
        });
    }

    public function verifyPayment(RoomSession $session, TerminalConnection $connection, int $categoryPaymentTypeId, string $referenceNumber): array
    {
        if ($breakBlock = $this->blockedByBreak($session)) {
            return $this->failure($breakBlock);
        }

        $attempt = $this->currentCalledAttempt($session);

        if (is_string($attempt)) {
            return $this->failure($attempt);
        }

        $category = $this->categoryFor($attempt);
        $type = CategoryPaymentType::where('category_id', $category->id)->find($categoryPaymentTypeId);

        if (! $type) {
            return $this->failure('Unknown payment type.');
        }

        try {
            $this->paymentVerificationService->verifyType($attempt, $type, $referenceNumber, $connection->panelist_user_id, $connection);
        } catch (DuplicatePaymentReferenceException $e) {
            return $this->failure($e->getMessage());
        }

        return ['ok' => true];
    }

    private function categoryFor(PresentationAttempt $attempt): PresentationCategory
    {
        $attempt->loadMissing('attemptSchedule.presentationDateRoom.presentationDate.category.categoryPaymentSetting', 'attemptSchedule.presentationDateRoom.presentationDate.category.categoryPaymentTypes');

        return $attempt->attemptSchedule->presentationDateRoom->presentationDate->category;
    }

    public function start(RoomSession $session, TerminalConnection $connection): array
    {
        if ($breakBlock = $this->blockedByBreak($session)) {
            return $this->failure($breakBlock);
        }

        $attempt = $this->currentCalledAttempt($session);

        if (is_string($attempt)) {
            return $this->failure($attempt);
        }

        $attempt->loadMissing(
            'presentationRun',
            'paymentVerifications.paymentStatus',
            'attemptSchedule.presentationDateRoom.presentationDate.category.categoryPaymentSetting',
            'attemptSchedule.presentationDateRoom.presentationDate.category.categoryPaymentTypes',
            'attemptPanelAssignments.assignmentKind',
            'attemptPanelAssignments.assignmentStatus',
        );

        // User-directed 2026-09-13: a group whose panel no longer matches its
        // room's panelist_count can't be started until it's reassigned. The
        // count is editable in Presentation Setup after assignment (it applies
        // to every ongoing/upcoming room), so a panel sized for the old
        // requirement would otherwise quietly present understaffed or
        // overstaffed. Same rule the Group & Panel Assignment roster flags in
        // its Panel column, via one shared service method.
        $panelIssue = $this->panelAssignmentService->panelRequirementIssue($attempt);

        if ($panelIssue !== null) {
            return $this->failure($panelIssue);
        }

        $category = $attempt->attemptSchedule->presentationDateRoom->presentationDate->category;

        if (! $this->paymentVerificationService->allSatisfied($category, $attempt)) {
            return $this->failure('Verify payment before starting this presentation.');
        }

        // User-directed 2026-08-21: Start must not proceed with an empty
        // panelist seat — unlike Call Next (allTerminalsActive() above,
        // device-claimed only, panelist login not required yet at that
        // point), an actual presentation can't begin evaluation-ready with
        // a terminal nobody's occupying. Same "every enabled terminal, not
        // specifically every assigned panelist" shape as
        // allTerminalsActive() — a backup/approved-substitute filling any
        // seat still counts, matching recordPanelParticipation()'s own
        // substitute-is-fine posture below.
        if (! $this->allTerminalsStaffed($session)) {
            return $this->failure('All panelists must be connected before starting this presentation.');
        }

        $run = $attempt->presentationRun;

        if (! $run) {
            return $this->failure('No active run found for this group — call it again.');
        }

        $now = now();

        try {
            return DB::transaction(function () use ($session, $attempt, $run, $connection, $now) {
                $run->update([
                    'started_at' => $now,
                    'timer_status_id' => TimerStatus::where('code', 'WITHIN_TIME')->firstOrFail()->id,
                    'last_action_at' => $now,
                ]);

                $attempt->update(['presentation_status_id' => PresentationStatus::where('code', 'ONGOING')->firstOrFail()->id]);

                $session->update([
                    'room_session_status_id' => RoomSessionStatus::where('code', 'ACTIVE')->firstOrFail()->id,
                    'started_at' => $session->started_at ?? $now,
                ]);

                $this->logAction($run, 'START', $connection, null, null, $now);
                $this->recordPanelParticipation($session, $attempt, $now);

                // Blocks the whole Start if the category has no evaluation
                // form assigned — there would be nothing for a connected
                // panelist to fill out otherwise. Runs last, after
                // participation rows exist, since startFor() creates one
                // submission per participation row.
                $evaluationResult = $this->evaluationSubmissionService->startFor($attempt, $session);

                if (! $evaluationResult['ok']) {
                    throw new \RuntimeException($evaluationResult['error']);
                }

                return ['ok' => true];
            });
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage());
        }
    }

    public function pause(RoomSession $session, TerminalConnection $connection, ?string $remarks): array
    {
        $attempt = $this->currentAttemptWithRun($session, ['ONGOING']);

        if (is_string($attempt)) {
            return $this->failure($attempt);
        }

        $run = $attempt->presentationRun;

        if (! in_array($run->timerStatus?->code, ['WITHIN_TIME', 'EXTENDED'], true)) {
            return $this->failure('This presentation is not currently running.');
        }

        $now = now();

        return DB::transaction(function () use ($session, $attempt, $run, $connection, $remarks, $now) {
            $run->presentationPauses()->create([
                'paused_at' => $now,
                'remarks' => $remarks,
                'paused_by' => $connection->panelist_user_id,
            ]);

            $run->update(['timer_status_id' => TimerStatus::where('code', 'PAUSED')->firstOrFail()->id, 'last_action_at' => $now]);
            $attempt->update(['presentation_status_id' => PresentationStatus::where('code', 'PAUSED')->firstOrFail()->id]);
            $session->update(['room_session_status_id' => RoomSessionStatus::where('code', 'PAUSED')->firstOrFail()->id]);

            $this->logAction($run, 'PAUSE', $connection, null, $remarks, $now);

            return ['ok' => true];
        });
    }

    public function resume(RoomSession $session, TerminalConnection $connection): array
    {
        $attempt = $this->currentAttemptWithRun($session, ['PAUSED']);

        if (is_string($attempt)) {
            return $this->failure($attempt);
        }

        $run = $attempt->presentationRun;

        if ($run->timerStatus?->code !== 'PAUSED') {
            return $this->failure('This presentation is not currently paused.');
        }

        $now = now();
        $openPause = $run->presentationPauses()->whereNull('resumed_at')->latest('id')->first();
        $pausedSeconds = $openPause ? $openPause->paused_at->diffInSeconds($now) : 0;
        $totalPaused = $run->total_paused_seconds + $pausedSeconds;
        $elapsed = $run->started_at->diffInSeconds($now) - $totalPaused;
        $timerCode = $elapsed > $run->configured_duration_seconds ? 'EXTENDED' : 'WITHIN_TIME';

        return DB::transaction(function () use ($session, $attempt, $run, $openPause, $totalPaused, $timerCode, $connection, $now) {
            $openPause?->update(['resumed_at' => $now, 'resumed_by' => $connection->panelist_user_id]);

            $run->update([
                'total_paused_seconds' => $totalPaused,
                'timer_status_id' => TimerStatus::where('code', $timerCode)->firstOrFail()->id,
                'last_action_at' => $now,
            ]);

            $attempt->update(['presentation_status_id' => PresentationStatus::where('code', 'ONGOING')->firstOrFail()->id]);
            $session->update(['room_session_status_id' => RoomSessionStatus::where('code', 'ACTIVE')->firstOrFail()->id]);

            $this->logAction($run, 'RESUME', $connection, null, null, $now);

            return ['ok' => true];
        });
    }

    public function complete(RoomSession $session, TerminalConnection $connection): array
    {
        $attempt = $this->currentAttemptWithRun($session, ['ONGOING']);

        if (is_string($attempt)) {
            return $this->failure($attempt);
        }

        $run = $attempt->presentationRun;

        if (! $run->started_at) {
            return $this->failure('Start the presentation before completing it.');
        }

        if (! $this->evaluationSubmissionService->allSubmitted($attempt)) {
            return $this->failure('All panelists must submit their evaluations before this presentation can be completed.');
        }

        $now = now();
        $actualDuration = max(0, $run->started_at->diffInSeconds($now) - $run->total_paused_seconds);
        $extendedSeconds = max(0, $actualDuration - $run->configured_duration_seconds);

        $result = DB::transaction(function () use ($session, $attempt, $run, $connection, $actualDuration, $extendedSeconds, $now) {
            $run->update([
                'completed_at' => $now,
                'actual_duration_seconds' => $actualDuration,
                'extended_seconds' => $extendedSeconds,
                'timer_status_id' => TimerStatus::where('code', 'COMPLETED')->firstOrFail()->id,
                'last_action_at' => $now,
            ]);

            $attempt->update([
                'presentation_status_id' => PresentationStatus::where('code', 'COMPLETED')->firstOrFail()->id,
                'completed_at' => $now,
                // The Lead/Chair's own submitted verdict becomes the
                // attempt's official outcome (user-directed 2026-09-13) —
                // see EvaluationSubmissionService::leadOutcomeFor(). Until
                // now nothing on this path ever set final_outcome_id, so a
                // group completed through the tablet carried no outcome at
                // all and could not be classified as passed/re-defense/failed.
                'final_outcome_id' => $this->evaluationSubmissionService->leadOutcomeFor($attempt),
            ]);

            $session->update([
                'current_attempt_id' => null,
                'room_session_status_id' => RoomSessionStatus::where('code', 'WAITING')->firstOrFail()->id,
            ]);

            AttemptPanelParticipation::where('presentation_attempt_id', $attempt->id)
                ->whereNull('participation_ended_at')
                ->update(['participation_ended_at' => $now]);

            $this->logAction($run, 'COMPLETE', $connection, null, null, $now);

            return ['ok' => true];
        });

        // A group that ran into its break leaves the room on that break the
        // moment it is done, not at the next poll.
        $this->roomBreakService->sync($session->fresh());

        return $result;
    }

    public function defer(RoomSession $session, TerminalConnection $connection, int $reasonId, ?string $remarks): array
    {
        if ($breakBlock = $this->blockedByBreak($session)) {
            return $this->failure($breakBlock);
        }

        $attempt = $this->currentAttemptWithRun($session, ['CALLED', 'ONGOING', 'PAUSED']);

        if (is_string($attempt)) {
            return $this->failure($attempt);
        }

        return DB::transaction(fn () => $this->deferCurrentAttempt($session, $attempt, $connection, $reasonId, $remarks, now()));
    }

    /**
     * Runs the same queue mechanics QueueAdjustmentService already uses for
     * an admin-initiated defer (renumber, queue_adjustments audit row), then
     * layers on the presentation_status flip and room_session release that
     * only apply when the deferred attempt was the room's current one. Any
     * defer reason is allowed here, including a payment concern — Admin's
     * Group & Panel Assignment Deferred tab lets an Admin verify payment on
     * any deferred group regardless of why it was deferred, not only ones
     * flagged with a payment-specific reason.
     */
    private function deferCurrentAttempt(RoomSession $session, PresentationAttempt $attempt, TerminalConnection $connection, int $reasonId, ?string $remarks, \DateTimeInterface $now): array
    {
        $attempt->loadMissing('attemptSchedule.queueEntry', 'presentationRun');
        $entry = $attempt->attemptSchedule->queueEntry;

        if (! $entry) {
            return $this->failure('This group has no active queue entry to defer.');
        }

        // Panelist-initiated, so the Administrators are notified.
        $queueResult = $this->queueAdjustmentService->defer($entry, $reasonId, $remarks, $connection->panelist_user_id, notifyAdmins: true, fromRoomSession: true);

        if (! $queueResult['ok']) {
            return $queueResult;
        }

        $attempt->update(['presentation_status_id' => PresentationStatus::where('code', 'DEFERRED')->firstOrFail()->id]);

        $session->update([
            'current_attempt_id' => null,
            'room_session_status_id' => RoomSessionStatus::where('code', 'WAITING')->firstOrFail()->id,
        ]);

        if ($attempt->presentationRun) {
            $this->logAction($attempt->presentationRun, 'DEFER', $connection, $reasonId, $remarks, $now);
        }

        return ['ok' => true];
    }

    /**
     * Creates/refreshes one attempt_panel_participations row per currently
     * connected terminal in the room at the moment Start is pressed —
     * evaluation submissions key off this row, not off terminal_connections
     * directly. A connected panelist with no matching attempt_panel_assignment
     * for this specific attempt (but eligible for the room generally, e.g. a
     * backup filling a different group's seat) is recorded as an approved
     * substitute rather than rejected — TerminalConnectionService already
     * gate-kept room eligibility before they were allowed to connect at all.
     *
     * User-directed 2026-09-07: terminal_type_id here is no longer read off
     * the physical terminal (every room_terminal is created with the same
     * EVALUATION type now — see EventActivationService::start()) — it's
     * resolved per connected panelist instead, via
     * TerminalConnectionService::isLead(), so this audit row still
     * accurately records "acted as Lead for this presentation" even though
     * the Lead can now sit at any seat.
     */
    private function recordPanelParticipation(RoomSession $session, PresentationAttempt $attempt, \DateTimeInterface $now): void
    {
        $terminals = $session->roomTerminals()->with(['terminalConnections' => fn ($q) => $q->whereNull('disconnected_at')])->get();

        $assignedPanelistIds = AttemptPanelAssignment::where('presentation_attempt_id', $attempt->id)
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->pluck('panelist_user_id');

        $connectionService = app(TerminalConnectionService::class);
        $leadTypeId = TerminalType::where('code', 'LEAD')->firstOrFail()->id;
        $evaluationTypeId = TerminalType::where('code', 'EVALUATION')->firstOrFail()->id;

        foreach ($terminals as $terminal) {
            $connection = $terminal->terminalConnections->first();

            if (! $connection) {
                continue;
            }

            $terminalTypeId = $connectionService->isLead($session, $connection->panelist)
                ? $leadTypeId
                : $evaluationTypeId;

            AttemptPanelParticipation::updateOrCreate(
                [
                    'presentation_attempt_id' => $attempt->id,
                    'panelist_user_id' => $connection->panelist_user_id,
                ],
                [
                    'terminal_number' => $terminal->terminal_number,
                    'terminal_type_id' => $terminalTypeId,
                    'terminal_connection_id' => $connection->id,
                    'participation_started_at' => $now,
                    'participation_ended_at' => null,
                    'is_approved_substitute' => ! $assignedPanelistIds->contains($connection->panelist_user_id),
                ]
            );
        }
    }

    /**
     * $connection stays nullable defensively — presentation_actions.
     * performed_by is a required FK, so there's no real panelist to
     * attribute the row to without one; the row is skipped rather than
     * fabricating a performer, while the room_session/presentation_run
     * state changes themselves are still recorded either way. As of
     * 2026-09-07, RoomSessionController::requireLeadConnection() always
     * resolves a real connected Lead before calling any of the control
     * actions (including Call Next, which used to be allowed from an
     * empty device-claimed Terminal 1), so this null path is no longer
     * actually reachable from that controller — kept as a defensive
     * fallback for any other caller. (End Room Session itself was removed
     * the same day — a room session is now only ever closed via the
     * Admin's End Event action, EventActivationService::end().)
     */
    private function logAction(PresentationRun $run, string $actionTypeCode, ?TerminalConnection $connection, ?int $reasonId, ?string $remarks, \DateTimeInterface $now): void
    {
        if (! $connection) {
            return;
        }

        PresentationAction::create([
            'presentation_run_id' => $run->id,
            'action_type_id' => PresentationActionType::where('code', $actionTypeCode)->firstOrFail()->id,
            'performed_by' => $connection->panelist_user_id,
            'terminal_connection_id' => $connection->id,
            'reason_id' => $reasonId,
            'remarks' => $remarks,
            'performed_at' => $now,
        ]);
    }

    /**
     * User-directed 2026-08-18: "active" here means device-claimed
     * (room_terminals.device_identifier set at the terminal-picker step),
     * not that a panelist is currently logged into the seat — Call Next
     * gates on every enabled terminal in the room having been physically
     * set up, regardless of who (if anyone) is presently connected to
     * each one.
     */
    public function allTerminalsActive(RoomSession $session): bool
    {
        $terminals = $session->roomTerminals()->where('is_enabled', true)->get();

        return $terminals->isNotEmpty() && $terminals->every(fn ($terminal) => $terminal->device_identifier !== null);
    }

    /**
     * User-directed 2026-08-21: gates Start (unlike allTerminalsActive()
     * above, which only requires the physical tablets to be device-claimed
     * and gates Call Next instead) — every enabled
     * terminal in the room must have an actual panelist currently
     * connected (terminal_connections.disconnected_at null), not just a
     * claimed device. Room-scoped, not attempt-specific: whoever occupies a
     * seat satisfies it, matching recordPanelParticipation()'s own
     * approved-substitute posture rather than requiring the exact
     * assigned/backup panelists for this one attempt.
     */
    public function allTerminalsStaffed(RoomSession $session): bool
    {
        $terminals = $session->roomTerminals()
            ->where('is_enabled', true)
            ->with(['terminalConnections' => fn ($q) => $q->whereNull('disconnected_at')])
            ->get();

        return $terminals->isNotEmpty() && $terminals->every(fn ($terminal) => $terminal->terminalConnections->isNotEmpty());
    }

    private function blockedByBreak(RoomSession $session): ?string
    {
        return $this->roomBreakService->onBreak($session)
            ? 'This room is on break — end the break to continue.'
            : null;
    }

    private function blockedBySessionStatus(RoomSession $session): ?string
    {
        $code = $session->roomSessionStatus?->code;

        if (in_array($code, ['CLOSED', 'FINISHED'], true)) {
            return 'This room session has already ended for the day.';
        }

        if ($code === 'PENDING_CLOSURE') {
            return 'This room is closing — no further groups may be called here.';
        }

        return null;
    }

    /**
     * Resolves the room's current attempt and checks it's CALLED — the gate
     * shared by Start, Verify Payment, and Refer Payment (all three only
     * make sense between a Call and a Start). Returns an error string
     * instead of the attempt on failure so callers can do
     * `if (is_string($attempt)) return $this->failure($attempt);`.
     */
    private function currentCalledAttempt(RoomSession $session): PresentationAttempt|string
    {
        $attempt = $this->currentAttemptWithRun($session, ['CALLED']);

        return $attempt;
    }

    /**
     * @param  string[]  $allowedStatusCodes
     */
    private function currentAttemptWithRun(RoomSession $session, array $allowedStatusCodes): PresentationAttempt|string
    {
        $session->loadMissing('currentAttempt.presentationStatus', 'currentAttempt.presentationRun.timerStatus');

        $attempt = $session->currentAttempt;

        if (! $attempt) {
            return 'No group is currently called in this room.';
        }

        $code = $attempt->presentationStatus?->code;

        if (! in_array($code, $allowedStatusCodes, true)) {
            return match ($code) {
                'COMPLETED', 'ABSENT', 'CANCELLED' => 'This group\'s presentation has already ended.',
                'DEFERRED' => 'This group has been deferred.',
                'CALLED' => 'Start the presentation first.',
                'ONGOING' => 'This presentation is currently in progress.',
                'PAUSED' => 'This presentation is currently paused.',
                default => 'This action is not available for this group right now.',
            };
        }

        return $attempt;
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
