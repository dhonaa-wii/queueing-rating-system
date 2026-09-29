<?php

namespace App\Services;

use App\Models\CategoryStatus;
use App\Models\EventDateStatus;
use App\Models\EventStatus;
use App\Models\Notification;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use App\Models\PresentationEvent;
use App\Models\PresentationPause;
use App\Models\PresentationAction;
use App\Models\PresentationActionType;
use App\Models\PresentationRun;
use App\Models\QueueEntry;
use App\Models\RoomSession;
use App\Models\RoomSessionStatus;
use App\Models\RoomTerminal;
use App\Models\TerminalType;
use Illuminate\Support\Facades\DB;

/**
 * Admin-side Event Activation (Phase 1 of Live Monitoring — see
 * docs/functional-spec.md §6.8/§9.9). Day-to-day live control (Call Next,
 * Start, Complete, Defer) belongs to the panelist at Terminal 1 once
 * Presentation Control is built (user-directed 2026-08-05: "panel will be
 * responsible on live btns") — that panelist terminal/QR-join flow is a
 * separate, later phase. This service only covers what's admin's: starting
 * the event once its scheduled time has actually arrived, ending it, and an
 * always-available supervisory pause/resume override independent of
 * whatever a panelist is or isn't doing.
 */
class EventActivationService
{
    public function __construct(
        private QueueAdjustmentService $queueAdjustments,
        private NotificationService $notifications,
        private RoomSessionAccountService $roomSessionAccounts,
        private ReDefenseService $reDefense,
    ) {
    }

    /**
     * No scheduler exists in this app, so — same "recomputed as a side
     * effect of a frequently-hit page load" convention used everywhere else
     * (PresentationCategory::refreshStatus(), QueueGenerationService::
     * autoGenerateIfEligible()) — this is called from
     * Admin\PanelSubstitutionController::index(), the global JSON endpoint
     * the topbar notification bell polls every 20s from every admin page.
     * Scans every non-terminal date across every non-archived category:
     * raises one PRESENTATION_DATE_OVERDUE notification (deduplicated by an
     * existing unread one for that exact date) the moment isOverdue() turns
     * true, and clears any such notification once the date is no longer
     * overdue — either because it was edited (Presentation Setup) or
     * actually started, matching NotificationService::markRelatedRead()'s
     * existing "resolved elsewhere" pattern.
     */
    public function flagOverdueDates(): void
    {
        $dates = PresentationDate::whereHas('category', fn ($q) => $q->whereHas(
            'categoryStatus', fn ($q2) => $q2->whereNotIn('code', ['ARCHIVED'])
        ))
            ->whereHas('eventDateStatus', fn ($q) => $q->whereNotIn('code', ['ACTIVE', 'COMPLETED', 'CANCELLED']))
            ->whereNull('activated_at')
            ->with('eventDateStatus')
            ->get();

        foreach ($dates as $date) {
            if ($date->isOverdue()) {
                $alreadyNotified = Notification::where('notification_type', 'PRESENTATION_DATE_OVERDUE')
                    ->where('related_type', PresentationDate::class)
                    ->where('related_id', $date->id)
                    ->whereNull('read_at')
                    ->exists();

                if (! $alreadyNotified) {
                    $this->notifications->notifyRole(
                        'ADMIN',
                        'PRESENTATION_DATE_OVERDUE',
                        'Presentation date needs updating',
                        $this->dateLabel($date) . ' — never started.',
                        $date
                    );
                }
            } else {
                $this->notifications->markRelatedRead($date);
            }
        }
    }

    /**
     * User-directed 2026-08-23: an ACTIVE date is deliberately allowed to
     * run past its configured event_end_time — that's how an admin
     * extends a day to keep taking groups moved over from a later date's
     * queue — but it must not be able to run forever if the admin simply
     * forgets to click End Event. "Auto-ends at 11:59pm" is approximated
     * as "the calendar day has rolled over" (no scheduler exists in this
     * app — same page-load-driven convention as flagOverdueDates(), called
     * from the same place), which is exactly the same boundary in
     * practice: the date's own day still has its full 11:59pm to be
     * extended into, and this only fires once that day is truly over.
     * Reuses end() as-is, including its existing guard that refuses while
     * a room still shows a real presentation in progress — this never
     * force-aborts a live presentation at midnight, it just leaves that
     * date to be retried (harmlessly, idempotently) on the next poll once
     * whatever's in progress is completed or deferred. A successful
     * end() already runs the exact same carry-unfinished-to-next-day /
     * requeue-deferred-to-category-end sweep
     * (QueueAdjustmentService::processEndOfDay()) a manual End Event does
     * — auto-ending isn't a different code path, just a different trigger.
     *
     * A blocked end() is not left silent (2026-08-23 correction, caught
     * against real data: a category started 2026-08-17 and still showing
     * ACTIVE on 2026-08-23 with no notification at all — turned out
     * end()'s pre-existing "a room still has a real presentation in
     * progress" guard was correctly refusing every poll, because one
     * attempt had been sitting at CALLED for 6 days with nothing to ever
     * resolve it, and nothing told the admin why the day wouldn't close).
     * Every blocked attempt raises a deduplicated EVENT_AUTO_END_BLOCKED
     * notification naming the actual reason (the same message start()/
     * end() already produce) with a link to Live Monitoring for that
     * date; a later poll that succeeds clears it via markRelatedRead(),
     * same "resolved elsewhere" pattern as flagOverdueDates().
     */
    public function autoEndPastDay(): void
    {
        $dates = PresentationDate::whereHas('eventDateStatus', fn ($q) => $q->where('code', 'ACTIVE'))
            ->whereDate('presentation_date', '<', now()->toDateString())
            ->with('presentationEvent')
            ->get();

        foreach ($dates as $date) {
            $event = $date->presentationEvent;

            if (! $event || ! $event->started_by) {
                continue;
            }

            $result = $this->end($date, $event->started_by);

            if ($result['ok']) {
                $this->notifications->markRelatedRead($date);
                continue;
            }

            $alreadyNotified = Notification::where('notification_type', 'EVENT_AUTO_END_BLOCKED')
                ->where('related_type', PresentationDate::class)
                ->where('related_id', $date->id)
                ->whereNull('read_at')
                ->exists();

            if (! $alreadyNotified) {
                $this->notifications->notifyRole(
                    'ADMIN',
                    'EVENT_AUTO_END_BLOCKED',
                    'Presentation day could not close automatically',
                    $this->dateLabel($date) . ' — presentation still in progress.',
                    $date
                );
            }
        }
    }

    /**
     * Companion to autoEndPastDay(), for the other overdue case: a date
     * that was never started at all (no activated_at, so there's no
     * PresentationEvent for end() to close — this doesn't call end()).
     * User-directed 2026-08-25: isOverdue()/flagOverdueDates() already flag
     * this case and block Start, but until now the date's queued groups
     * just sat there indefinitely with no way forward except a manual
     * Group & Panel Assignment Transfer. This applies the same "the day is
     * over, move on" logic autoEndPastDay() applies to an ACTIVE date, on
     * the same day-rollover boundary (called from the same poll) — the
     * date still gets its full calendar day in case the admin shows up
     * late and starts it normally.
     *
     * The date is marked CANCELLED, not COMPLETED — it never actually ran
     * as an event, unlike a date end() closes out. CANCELLED is one of
     * deriveStatus()'s two sticky terminal codes, so this holds on its own
     * with no further writes needed. completed_at is set anyway (even
     * though nothing "completed") purely so this date's rooms stop
     * counting as "still open" everywhere that already keys off
     * completed_at instead of the status code — QueueAdjustmentService::
     * lastOpenRoomInCategory() and this service's own
     * flagUnscheduledGroups() — matching how end() itself closes a date
     * out.
     *
     * Reuses QueueAdjustmentService::processEndOfDay() exactly as end()
     * does — same carry-unfinished-to-next-day / requeue-deferred sweep,
     * just triggered without ever having started. There's no
     * PresentationEvent/started_by to attribute the resulting queue
     * adjustments to (queue_adjustments.approved_by is a required FK), so
     * this attributes them to the category's own creating admin instead
     * (PresentationCategory::actingUserId() — its creator, or a stand-in if that admin was deleted) — the closest
     * thing to a responsible actor for a category-level automatic action.
     */
    public function autoCancelNeverStarted(): void
    {
        $dates = PresentationDate::whereHas('eventDateStatus', fn ($q) => $q->whereIn('code', ['PLANNED', 'STANDBY']))
            ->whereNull('activated_at')
            ->whereDate('presentation_date', '<', now()->toDateString())
            ->whereHas('category', fn ($q) => $q->whereHas(
                'categoryStatus', fn ($q2) => $q2->where('code', '!=', 'ARCHIVED')
            ))
            ->with('category')
            ->get();

        foreach ($dates as $date) {
            $performedByUserId = $date->category->actingUserId();

            DB::transaction(function () use ($date) {
                $cancelledStatus = EventDateStatus::where('code', 'CANCELLED')->firstOrFail();

                $date->update([
                    'event_date_status_id' => $cancelledStatus->id,
                    'completed_at' => now(),
                ]);

                // Normally a no-op here — a date reaching this method was
                // never started (whereNull('activated_at') above), so
                // startRoom() never ran for it — kept for the same
                // "ended/completed/cancelled" belt-and-suspenders coverage
                // as end()'s own call.
                $this->roomSessionAccounts->removeForDate($date);
            });

            $this->queueAdjustments->processEndOfDay($date, $performedByUserId);
            $this->notifications->markRelatedRead($date);
        }
    }

    /**
     * User-directed 2026-08-23 (the same "no next date → groups stay in
     * the category, admin must add a new schedule" fallback described
     * when auto-end was built — this is the notification for it, which
     * hadn't actually been raised anywhere until now). Scans every
     * non-archived category for a non-terminal queue entry still sitting
     * in a room whose presentation date has already COMPLETED (i.e.
     * QueueAdjustmentService::processEndOfDay() had nowhere to carry it
     * to) and raises one deduplicated CATEGORY_UNSCHEDULED_GROUPS
     * notification per category, linking to that category's Presentation
     * Setup Schedules tab. Clears via markRelatedRead() once nothing is
     * stuck anymore for that category — whether because a new date/room
     * was added and the groups were manually transferred there (Group &
     * Panel Assignment's existing Transfer action already allows moving
     * a group into any not-yet-activated room in the same category — no
     * new relocation mechanism was needed for that), or any other way
     * the attempts reached a resolution.
     */
    public function flagUnscheduledGroups(): void
    {
        // Every category with a date configured: a finished day can strand
        // groups, and so can a schedule that is simply too small for them.
        $categoryIds = PresentationDate::distinct()->pluck('category_id');

        $categories = PresentationCategory::whereIn('id', $categoryIds)
            ->whereHas('categoryStatus', fn ($q) => $q->where('code', '!=', 'ARCHIVED'))
            ->get();

        foreach ($categories as $category) {
            if ($this->hasUnscheduledGroups($category)) {
                $alreadyNotified = Notification::where('notification_type', 'CATEGORY_UNSCHEDULED_GROUPS')
                    ->where('related_type', PresentationCategory::class)
                    ->where('related_id', $category->id)
                    ->whereNull('read_at')
                    ->exists();

                if (! $alreadyNotified) {
                    $this->notifications->notifyRole(
                        'ADMIN',
                        'CATEGORY_UNSCHEDULED_GROUPS',
                        'Category needs a new schedule',
                        "{$category->name} — groups waiting, no open date.",
                        $category
                    );
                }
            } else {
                $this->notifications->markRelatedRead($category);
            }
        }
    }

    /**
     * Public — also read by Admin\PanelSubstitutionController::index() to
     * compute CATEGORY_UNSCHEDULED_GROUPS' own linkResolved, same as
     * PresentationDate::isOverdue() is read there for the overdue type.
     */
    public function hasUnscheduledGroups(PresentationCategory $category): bool
    {
        // Parked on a day that is over…
        $onFinishedDay = QueueEntry::whereHas('attemptSchedule.presentationDateRoom.presentationDate', fn ($q) => $q
                ->where('category_id', $category->id)
                ->whereNotNull('completed_at')
            )
            ->whereHas('attemptSchedule.presentationAttempt.presentationStatus', fn ($q) => $q->where('is_terminal', false))
            ->exists();

        if ($onFinishedDay) {
            return true;
        }

        // …or on a day that is fine but has no time left for them
        // (AttemptSchedule::hasNoSlot()) — the configured dates and rooms are full.
        return QueueEntry::whereNull('removed_at')
            ->whereHas('attemptSchedule', fn ($q) => $q
                ->whereNull('planned_start_at')
                ->whereHas('presentationDateRoom.presentationDate', fn ($dq) => $dq->where('category_id', $category->id))
            )
            ->whereHas('attemptSchedule.presentationAttempt.presentationStatus', fn ($q) => $q
                ->where('is_terminal', false)
                ->whereNotIn('code', ['CALLED', 'ONGOING', 'PAUSED']))
            ->exists();
    }

    /**
     * Shared short "{category} — {date}" label reused by every
     * PresentationDate-related notification this service raises — keeps
     * each scenario's own message to one terse trailing clause (matching
     * the existing GROUP_DEFERRED/PANEL_SUBSTITUTION_REQUESTED style)
     * instead of a paragraph explaining the reasoning; the title already
     * names the scenario and the notification itself is a clickable link
     * straight to where it's resolved (see Admin\PanelSubstitutionController
     * ::index()), so the message only needs to name which date.
     */
    private function dateLabel(PresentationDate $date): string
    {
        $date->loadMissing('category');

        return "{$date->category->name} — {$date->presentation_date->format('M j, Y')}";
    }

    /**
     * Ends the event: closes every room session and marks the date
     * COMPLETED. Blocked only while a room genuinely has a presentation
     * actually underway — ONGOING (running) or PAUSED (admin/panel
     * override mid-run), per App\Services\PresentationControlService's own
     * status vocabulary. CALLED is deliberately NOT blocking (2026-08-23
     * correction, user-caught against real data: a group that was called
     * but never actually started is still "unfinished," not "in
     * progress" — functionally no different from any other still-queued
     * group, and QueueAdjustmentService::processEndOfDay()'s carry-over
     * sweep already treats it that way once end() lets it through).
     *
     * No longer has a button of its own (user-directed 2026-09-13 — the
     * day-level Start/End Event buttons were removed in favour of the
     * per-room ones). Reached only from endRoom(), once the day's last room
     * closes, and from autoEndPastDay()'s midnight sweep.
     */
    private function end(PresentationDate $date, int $performedByUserId): array
    {
        $date->loadMissing('presentationEvent.eventStatus', 'presentationEvent.roomSessions.currentAttempt.presentationStatus');

        $event = $date->presentationEvent;

        if (! $event || $event->eventStatus?->code !== 'ACTIVE') {
            return $this->failure('This date does not have an active event to end.');
        }

        $stillOngoing = $event->roomSessions->contains(
            fn ($session) => $session->currentAttempt && in_array($session->currentAttempt->presentationStatus?->code, ['ONGOING', 'PAUSED'], true)
        );

        if ($stillOngoing) {
            return $this->failure('At least one room still has a presentation in progress — it must be completed or deferred first.');
        }

        DB::transaction(function () use ($date, $event, $performedByUserId) {
            $completedEventStatus = EventStatus::where('code', 'COMPLETED')->firstOrFail();
            $closedStatus = RoomSessionStatus::where('code', 'CLOSED')->firstOrFail();
            $now = now();

            $event->update([
                'event_status_id' => $completedEventStatus->id,
                'ended_by' => $performedByUserId,
                'ended_at' => $now,
            ]);

            // current_attempt_id is nulled out here too — a room session
            // can now reach this point still pointing at a CALLED (never
            // actually started) attempt, and that attempt is about to be
            // relocated to a different room/day by processEndOfDay()
            // below, so a CLOSED session must not keep referencing it.
            // Only sessions still open: a room already closed through
            // End Room keeps its own real ended_at/ended_by_user_id.
            RoomSession::where('presentation_event_id', $event->id)->whereNull('ended_at')->update([
                'room_session_status_id' => $closedStatus->id,
                'ended_at' => $now,
                'ended_by_user_id' => $performedByUserId,
                'current_attempt_id' => null,
            ]);

            $date->update(['completed_at' => $now]);
            $date->refreshStatus();

            // User-directed 2026-09-07: the day's room login account(s)
            // stop existing the moment the day itself does — see
            // RoomSessionAccountService's class doc.
            $this->roomSessionAccounts->removeForDate($date);
        });

        // Run outside the transaction above (and its own per-entry
        // transactions, see QueueAdjustmentService::processEndOfDay) so a
        // conflict on any one leftover group's move never rolls back the
        // event actually having ended.
        $summary = $this->queueAdjustments->processEndOfDay($date, $performedByUserId);

        return ['ok' => true, ...$summary];
    }

    /**
     * Per-room Start, user-directed 2026-09-12: a day no longer has to go
     * live all at once — each room gets its own Start/End, so a room whose
     * panel is actually present can begin while another is still setting
     * up. Since 2026-09-13 this is the only way a day goes live — the
     * day-level Start Event button was removed.
     *
     * Whichever room starts first is the one that activates the day: it
     * creates the date's single PresentationEvent and stamps activated_at,
     * and every room started afterwards joins that same event.
     */
    public function startRoom(PresentationDateRoom $room, int $performedByUserId): array
    {
        $room->loadMissing('roomUseStatus', 'presentationDate.eventDateStatus');

        $date = $room->presentationDate;
        $date->refreshStatus();
        $date->load('eventDateStatus', 'presentationEvent.eventStatus');

        $code = $date->eventDateStatus?->code;

        if (! in_array($code, ['STANDBY', 'ACTIVE'], true)) {
            return $this->failure(match ($code) {
                'PLANNED' => 'This date has not reached its scheduled start time yet.',
                'COMPLETED' => 'This date has already ended.',
                'CANCELLED' => 'This date has been cancelled.',
                default => 'This room cannot be started right now.',
            });
        }

        if ($date->isOverdue()) {
            return $this->failure('This date has already passed without being started. Update the presentation date in Presentation Setup before starting the event.');
        }

        if (! $room->roomUseStatus?->is_accepting_queue) {
            return $this->failure('This room has been removed from the date.');
        }

        if (! $room->panelist_count) {
            return $this->failure("Set the panelist count for {$room->room_name} before starting it.");
        }

        $existing = $room->roomSessions()->with('roomSessionStatus')->orderByDesc('id')->first();

        if ($existing) {
            return $this->failure($existing->ended_at
                ? "{$room->room_name} has already been closed for this day."
                : "{$room->room_name} has already been started.");
        }

        return DB::transaction(function () use ($date, $room, $performedByUserId) {
            $now = now();
            $event = $date->presentationEvent;

            if (! $event) {
                $event = PresentationEvent::create([
                    'presentation_date_id' => $date->id,
                    'event_status_id' => EventStatus::where('code', 'ACTIVE')->firstOrFail()->id,
                    'started_by' => $performedByUserId,
                    'started_at' => $now,
                ]);

                $date->update(['activated_at' => $now]);
                $date->refreshStatus();
            }

            $session = RoomSession::create([
                'presentation_event_id' => $event->id,
                'presentation_date_room_id' => $room->id,
                'room_session_status_id' => RoomSessionStatus::where('code', 'WAITING')->firstOrFail()->id,
                // The room's real start — what its "Start" card shows once
                // started. Call Next only fills started_at when still null,
                // so this is kept.
                'started_at' => $now,
            ]);

            $terminalTypeId = TerminalType::where('code', 'EVALUATION')->firstOrFail()->id;

            for ($terminalNumber = 1; $terminalNumber <= $room->panelist_count; $terminalNumber++) {
                RoomTerminal::create([
                    'room_session_id' => $session->id,
                    'terminal_number' => $terminalNumber,
                    'terminal_type_id' => $terminalTypeId,
                    'is_enabled' => true,
                ]);
            }

            $this->roomSessionAccounts->generateForRoom($date, $room, $performedByUserId);

            return ['ok' => true, 'session' => $session];
        });
    }

    /**
     * Per-room End. Closes one room and takes its login account with it,
     * leaving every other room in the day running. Blocked by the same
     * ONGOING/PAUSED test end() uses — a presentation genuinely underway
     * has to be completed or deferred first; a merely CALLED group does not
     * block (2026-08-23 correction, see end()'s own note).
     *
     * The day itself ends (end(): event COMPLETED, date completed_at,
     * carry-over of unfinished/deferred groups) only once every room still
     * on the date has been started and closed. A room that hasn't started
     * yet keeps the day live — otherwise closing the first room would
     * complete the day and leave a late-starting room unable to start at
     * all. A room that never starts is still swept up by autoEndPastDay()
     * at the day rollover.
     */
    public function endRoom(RoomSession $session, int $performedByUserId): array
    {
        $session->loadMissing(
            'roomSessionStatus',
            'currentAttempt.presentationStatus',
            'presentationDateRoom.presentationDate',
        );

        if ($session->ended_at || $session->roomSessionStatus?->code === 'CLOSED') {
            return $this->failure('This room has already been closed for this day.');
        }

        if ($session->currentAttempt && in_array($session->currentAttempt->presentationStatus?->code, ['ONGOING', 'PAUSED'], true)) {
            return $this->failure('This room still has a presentation in progress — it must be completed or deferred first.');
        }

        $room = $session->presentationDateRoom;
        $date = $room->presentationDate;

        DB::transaction(function () use ($session, $date, $room, $performedByUserId) {
            $session->update([
                'room_session_status_id' => RoomSessionStatus::where('code', 'CLOSED')->firstOrFail()->id,
                'ended_at' => now(),
                'ended_by_user_id' => $performedByUserId,
                'current_attempt_id' => null,
            ]);

            $this->roomSessionAccounts->removeForRoom($date, $room);
        });

        $roomsRemaining = $this->roomsStillToRun($date);

        if ($roomsRemaining->isNotEmpty()) {
            return ['ok' => true, 'dayEnded' => false, 'roomsRemaining' => $roomsRemaining->all()];
        }

        $result = $this->end($date->fresh(), $performedByUserId);

        return $result['ok']
            ? ['ok' => true, 'dayEnded' => true, ...$result]
            : ['ok' => true, 'dayEnded' => false, 'dayEndError' => $result['error']];
    }

    /**
     * Names of the rooms on this date that still keep the day live: every
     * room still accepting queue whose latest session is missing (never
     * started) or not yet closed.
     */
    public function roomsStillToRun(PresentationDate $date): \Illuminate\Support\Collection
    {
        return $date->presentationDateRooms()
            ->with('roomUseStatus', 'roomSessions')
            ->get()
            ->filter(fn ($room) => $room->roomUseStatus?->is_accepting_queue)
            ->filter(function ($room) {
                $session = $room->roomSessions->sortByDesc('id')->first();

                return ! $session || ! $session->ended_at;
            })
            ->pluck('room_name')
            ->values();
    }

    /**
     * Always-available supervisory override (user-directed 2026-08-05: not
     * gated on what the panelist is or isn't doing). presentation_actions.
     * presentation_run_id is a required FK, so this can only log a
     * PresentationAction when a live PresentationRun actually exists —
     * which nothing creates yet in this phase (Presentation Control isn't
     * built). The room_session_status flip to PAUSED still happens either
     * way; that's real, visible state on its own.
     */
    public function pauseRoomSession(RoomSession $session, int $performedByUserId, ?int $reasonId, ?string $remarks): array
    {
        $session->loadMissing('presentationEvent.eventStatus', 'roomSessionStatus', 'currentAttempt');

        if ($session->presentationEvent?->eventStatus?->code !== 'ACTIVE') {
            return $this->failure('This room session is not part of an active event.');
        }

        if ($session->roomSessionStatus?->code === 'PAUSED') {
            return $this->failure('This room session is already paused.');
        }

        return DB::transaction(function () use ($session, $performedByUserId, $reasonId, $remarks) {
            $pausedStatus = RoomSessionStatus::where('code', 'PAUSED')->firstOrFail();
            $now = now();

            $session->update(['room_session_status_id' => $pausedStatus->id]);

            $run = $session->current_attempt_id
                ? PresentationRun::where('presentation_attempt_id', $session->current_attempt_id)
                    ->whereHas('timerStatus', fn ($q) => $q->where('code', '!=', 'COMPLETED'))
                    ->first()
                : null;

            if ($run) {
                PresentationPause::create([
                    'presentation_run_id' => $run->id,
                    'paused_at' => $now,
                    'reason_id' => $reasonId,
                    'remarks' => $remarks,
                    'paused_by' => $performedByUserId,
                ]);

                PresentationAction::create([
                    'presentation_run_id' => $run->id,
                    'action_type_id' => PresentationActionType::where('code', 'EMERGENCY_OVERRIDE')->firstOrFail()->id,
                    'performed_by' => $performedByUserId,
                    'reason_id' => $reasonId,
                    'remarks' => $remarks,
                    'performed_at' => $now,
                ]);
            }

            return ['ok' => true];
        });
    }

    public function resumeRoomSession(RoomSession $session, int $performedByUserId): array
    {
        $session->loadMissing('roomSessionStatus', 'currentAttempt');

        if ($session->roomSessionStatus?->code !== 'PAUSED') {
            return $this->failure('This room session is not currently paused.');
        }

        return DB::transaction(function () use ($session, $performedByUserId) {
            $targetStatus = RoomSessionStatus::where('code', $session->current_attempt_id ? 'ACTIVE' : 'WAITING')->firstOrFail();
            $now = now();

            $session->update(['room_session_status_id' => $targetStatus->id]);

            $run = $session->current_attempt_id
                ? PresentationRun::where('presentation_attempt_id', $session->current_attempt_id)->first()
                : null;

            if ($run) {
                $openPause = PresentationPause::where('presentation_run_id', $run->id)->whereNull('resumed_at')->latest('id')->first();

                if ($openPause) {
                    $openPause->update(['resumed_at' => $now, 'resumed_by' => $performedByUserId]);
                }

                PresentationAction::create([
                    'presentation_run_id' => $run->id,
                    'action_type_id' => PresentationActionType::where('code', 'EMERGENCY_OVERRIDE')->firstOrFail()->id,
                    'performed_by' => $performedByUserId,
                    'performed_at' => $now,
                    'metadata' => ['source' => 'admin_override', 'action' => 'resume'],
                ]);
            }

            return ['ok' => true];
        });
    }

    /**
     * User-directed 2026-08-17: a category-wide "End Category" action,
     * separate from ending one date's event — none of deriveStatus()'s
     * auto-derivation ever assigns ACTIVE/COMPLETED (both are explicitly
     * carved out as "belong to modules that don't exist yet"), so without
     * this nothing ever moves a category to COMPLETED even after every one
     * of its dates/groups has actually finished. Gated on every group
     * having reached a final outcome — PresentationCategory::
     * hasUnresolvedAttempts() already defines "not yet done" as any
     * non-terminal presentation_status, the same vocabulary the rest of
     * this app uses (e.g. "mark unresolved groups absent at end of day").
     *
     * Corrected 2026-09-17, user-directed: this used to also require at
     * least one presentation_attempts row to exist at all, which meant a
     * category with a schedule but zero registered groups — or one whose
     * groups were all deleted — could never be ended, since it could never
     * satisfy that requirement. The actual rule the user wants is "no group
     * still needs to present": a category with no groups at all already
     * satisfies that trivially, same as one where every group reached a
     * terminal outcome (Pass or Failed — Failed is final, it just never
     * gets another attempt). A pending re-defense is the one case that
     * still blocks it despite its attempt row being COMPLETED, since that
     * group hasn't actually reached a final outcome yet — see
     * ReDefenseService::hasAwaiting(). Ending a category never deletes
     * anything; it only stops new groups/dates from being added to it
     * (PresentationCategory::isCompleted(), checked at each of those write
     * paths) and drops it out of Group & Panel Assignment's and Event
     * Control's category pickers, since there is nothing left to do in
     * either — it stays visible in Presentation Setup's category list and
     * in Reports.
     */
    public function completeCategory(PresentationCategory $category, int $performedByUserId): array
    {
        if ($reason = $this->completionBlockReason($category)) {
            return $this->failure($reason);
        }

        $completedStatus = CategoryStatus::where('code', 'COMPLETED')->firstOrFail();
        $category->update(['category_status_id' => $completedStatus->id]);

        return ['ok' => true];
    }

    /**
     * Null when "End Category" is allowed, otherwise the exact reason it
     * isn't — shared by completeCategory() itself and
     * LiveMonitoringController::show() (so the button's disabled tooltip
     * names the real blocker instead of a generic one-size-fits-all line).
     */
    public function completionBlockReason(PresentationCategory $category): ?string
    {
        $category->loadMissing('categoryStatus');
        $code = $category->categoryStatus?->code;

        if ($code === 'COMPLETED') {
            return 'This category has already been marked complete.';
        }

        if ($code === 'ARCHIVED') {
            return 'This category is archived.';
        }

        if ($category->hasUnresolvedAttempts()) {
            return 'At least one group has not finished presenting yet — every group must reach a final outcome first.';
        }

        if ($this->reDefense->hasAwaiting($category)) {
            return 'At least one group is awaiting a re-defense attempt — schedule or resolve it before ending the category.';
        }

        return null;
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
