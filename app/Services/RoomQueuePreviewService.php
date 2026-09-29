<?php

namespace App\Services;

use App\Models\AttemptSchedule;
use App\Models\PresentationDateRoom;

class RoomQueuePreviewService
{
    /**
     * Current / Called / Next preview for one room, sourced straight from
     * the already-generated queue (attempt_schedules/queue_entries) — real,
     * useful information as soon as a queue is generated, not only once
     * Presentation Control (a later phase) starts writing ONGOING/CALLED
     * statuses. "Current"/"Called" simply stay empty until that phase
     * exists; "Next" is real today. Carries each schedule's assigned panel
     * (ASSIGNED_PANELIST/BACKUP_PANELIST split).
     *
     * Extracted from Admin\LiveMonitoringController so Panelist\DashboardController
     * can reuse the identical query shape instead of duplicating it.
     */
    public function forRoom(PresentationDateRoom $room): array
    {
        $schedules = AttemptSchedule::where('presentation_date_room_id', $room->id)
            ->whereHas('queueEntry', fn ($query) => $query->whereNull('removed_at'))
            ->with([
                'presentationAttempt.presentationStatus',
                'presentationAttempt.researchGroup.students',
                'presentationAttempt.researchGroup.proposedTitles',
                'presentationAttempt.presentationRun',
                'queueEntry',
                'presentationAttempt.attemptPanelAssignments' => fn ($q) => $q->whereHas('assignmentStatus', fn ($sq) => $sq->whereNotIn('code', ['REPLACED', 'WITHDRAWN'])),
                'presentationAttempt.attemptPanelAssignments.panelist.profile',
                'presentationAttempt.attemptPanelAssignments.assignmentKind',
            ])
            ->get()
            ->sortBy(fn ($schedule) => $schedule->queueEntry->queue_number)
            ->values();

        $findByStatus = fn (array $codes) => $schedules->first(
            fn ($schedule) => in_array($schedule->presentationAttempt->presentationStatus->code, $codes, true)
        );

        $current = $findByStatus(['ONGOING', 'PAUSED']);
        $called = $findByStatus(['CALLED']);

        // Ordered list of everything still waiting to be called — 'next' stays
        // a single schedule (first of this list) for existing callers that
        // only ever needed one, while 'upcoming' lets the room-session tablet
        // preview a few groups ahead (Presentation Control's 3-group Standby
        // list, the Room Queue card's 2-across Next row).
        // A group with no slot ("to be scheduled" — the configured days are
        // full) is not callable: it has no place in today's running order.
        $upcoming = $schedules
            ->filter(fn ($schedule) => in_array($schedule->presentationAttempt->presentationStatus->code, ['SCHEDULED', 'QUEUED', 'READY_NEXT'], true)
                && ! $schedule->hasNoSlot($schedule->presentationAttempt))
            ->values();
        $next = $upcoming->first();

        // Once a group completes, current_attempt_id clears and this slot
        // would otherwise just go blank until the next call — shown instead
        // as "Last Completed" (queue-panel.blade.php) so the row still
        // carries useful information in that gap. Only relevant when nothing
        // is presently current/called, so it isn't computed otherwise.
        $lastCompleted = ($current || $called) ? null : $schedules
            ->filter(fn ($schedule) => $schedule->presentationAttempt->presentationStatus->code === 'COMPLETED')
            ->sortByDesc(fn ($schedule) => $schedule->presentationAttempt->completed_at)
            ->first();

        $this->applyExpectedTimes($room, $schedules, $current, $called);

        return [
            'current' => $current,
            'called' => $called,
            'next' => $next,
            'upcoming' => $upcoming,
            'lastCompleted' => $lastCompleted,
            'schedules' => $schedules,
        ];
    }

    /**
     * Sets expected_start_at/expected_end_at as plain runtime attributes on
     * each schedule (never persisted — same "compute fresh on every load"
     * posture as CapacityAnalysisService) so the tablet's Current/Called/Next
     * box and the full room queue list can show a live-adjusted forecast
     * instead of the fixed planned_start_at/planned_end_at generated once at
     * queue-build time. A terminal attempt shows what actually happened, not
     * what was planned (user-directed 2026-08-22: planned dates/times are
     * only ever the plan — once something has really occurred, the real
     * timestamp wins everywhere it's displayed) — COMPLETED uses the real
     * run's started_at/completed_at (falling back to the attempt's own
     * completed_at if a run was never created, e.g. an Admin override
     * complete with no Presentation Control run behind it). ABSENT/CANCELLED
     * have no writer anywhere yet that records a real timestamp, so those
     * still fall back to the plan — there's nothing real to show instead.
     * Everything non-terminal cascades forward from wherever the room's
     * timeline actually is
     * right now: the running attempt's real started_at + configured/paused/
     * extended seconds if one exists, otherwise the called attempt, otherwise
     * "now" — so a group running long visibly pushes every group behind it
     * later, and the reverse (an early finish) pulls them earlier.
     *
     * That cascade only applies while the day is actually running
     * (PresentationDate::isRunning()) — user-directed 2026-09-13: an ongoing
     * schedule shows the adjusted expected time, an upcoming one that has
     * not started shows the plan. The cursor starts at "now", so cascading
     * on a day that has not begun would drag tomorrow's queue onto today's
     * clock; on such a day expected simply mirrors planned, which keeps
     * every caller free to read expected_start_at unconditionally rather
     * than re-deciding the rule per screen.
     */
    private function applyExpectedTimes(PresentationDateRoom $room, $schedules, $current, $called): void
    {
        $scheduleSetting = $room->presentationDate?->category?->categoryScheduleSetting;
        $durationMinutes = $scheduleSetting?->duration_minutes ?? 30;

        if (! $room->presentationDate?->isRunning()) {
            foreach ($schedules as $schedule) {
                $this->applyPlannedOrRealTimes($schedule);
            }

            return;
        }

        $cursor = now();

        if ($current) {
            $run = $current->presentationAttempt->presentationRun;

            if ($run && $run->started_at) {
                $current->expected_start_at = $run->started_at;
                $current->expected_end_at = $run->started_at->copy()
                    ->addSeconds($run->configured_duration_seconds ?? ($durationMinutes * 60))
                    ->addSeconds($run->total_paused_seconds ?? 0)
                    ->addSeconds($run->extended_seconds ?? 0);
            } elseif ($current->planned_start_at && $current->planned_end_at) {
                $current->expected_start_at = $current->planned_start_at;
                $current->expected_end_at = $current->planned_end_at;
            } else {
                $current->expected_start_at = $cursor;
                $current->expected_end_at = $cursor->copy()->addMinutes($durationMinutes);
            }

            // A group that has run past its slot is still on stage right now,
            // so the room can't be free any earlier than now — without this the
            // groups behind it were forecast into the past for as long as it
            // overran.
            if ($current->expected_end_at->lt($cursor)) {
                $current->expected_end_at = $cursor->copy();
            }

            $cursor = $current->expected_end_at->copy();
        }

        if ($called) {
            // Called means it is at the room now, so it starts as soon as the
            // room is free — waiting out a break only if one is in progress.
            $start = $room->breakAt($cursor)?->planned_end_at?->copy() ?? $cursor->copy();

            $called->expected_start_at = $start;
            $called->expected_end_at = $start->copy()->addMinutes($durationMinutes);

            $cursor = $called->expected_end_at->copy();
        }

        foreach ($schedules as $schedule) {
            if ($schedule === $current || $schedule === $called) {
                continue;
            }

            if ($schedule->presentationAttempt->presentationStatus->is_terminal) {
                $this->applyPlannedOrRealTimes($schedule);

                continue;
            }

            if ($schedule->hasNoSlot($schedule->presentationAttempt)) {
                $schedule->expected_start_at = null;
                $schedule->expected_end_at = null;

                continue;
            }

            // The forecast steps over the room's breaks the same way the plan
            // does, so a group never shows a time that sits on one.
            $start = $room->slotStartClearOfBreaks($cursor, $durationMinutes);

            $schedule->expected_start_at = $start;
            $schedule->expected_end_at = $start->copy()->addMinutes($durationMinutes);

            $cursor = $schedule->expected_end_at->copy();
        }
    }

    /**
     * The no-forecast case: a row that has already happened is described by
     * its own real timestamps, anything else by its plan. Used both for a
     * terminal row on a running day and for every row on a day that has not
     * started, so those two paths can't drift.
     */
    private function applyPlannedOrRealTimes($schedule): void
    {
        $attempt = $schedule->presentationAttempt;

        if ($attempt->presentationStatus->code === 'COMPLETED' && ($attempt->presentationRun?->completed_at || $attempt->completed_at)) {
            $schedule->expected_start_at = $attempt->presentationRun?->started_at ?? $attempt->completed_at;
            $schedule->expected_end_at = $attempt->presentationRun?->completed_at ?? $attempt->completed_at;

            return;
        }

        // A group still to present on a day that can no longer run has no
        // time to show at all (user-directed 2026-09-16) — its plan is a slot
        // on a day that is over. Nulled here rather than left as the plan so
        // that no screen reading expected_start_at can accidentally advertise
        // it; what they show instead is AttemptSchedule::AWAITING_SHORT, keyed
        // off the same isAwaitingReschedule() test.
        if ($schedule->isAwaitingReschedule($attempt)) {
            $schedule->expected_start_at = null;
            $schedule->expected_end_at = null;

            return;
        }

        $schedule->expected_start_at = $schedule->planned_start_at;
        $schedule->expected_end_at = $schedule->planned_end_at;
    }
}
