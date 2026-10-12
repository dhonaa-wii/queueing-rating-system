<?php

namespace App\Services;

use App\Models\PresentationCategory;
use App\Models\PresentationDateRoom;
use App\Models\QueueEntry;
use Illuminate\Support\Carbon;

/**
 * A fast, always-visible rough capacity health-check — deliberately simpler
 * than QueueGenerationService::plan(), which does the exact per-room-window
 * round-robin that actual generation relies on. This service answers
 * "roughly, do we have enough room?" using a flat
 * days × rooms × slots-per-room-per-day formula, so it can stay a quick,
 * cheap, always-on-screen indicator rather than running the full scheduling
 * algorithm on every page load.
 *
 * Days, Rooms, Slots/Room/Day and Total Capacity only ever count ongoing
 * and upcoming days (PresentationDate::isOpenForScheduling()) and the rooms
 * assigned to those days — a day that already happened, or passed without
 * starting, can't absorb any more groups, so neither it nor its rooms
 * contribute to remaining capacity.
 * Correspondingly, open slots are measured against the groups still to
 * present (groupsToPresent()), not every registered group — a group that
 * already presented on a concluded day shouldn't count against the days
 * that are still open.
 */
class CapacityAnalysisService
{
    public function analyze(PresentationCategory $category): array
    {
        $totalDatesConfigured = $category->presentationDates()->count();

        $rooms = PresentationDateRoom::whereHas('presentationDate', fn ($query) => $query->where('category_id', $category->id))
            ->whereHas('roomUseStatus', fn ($query) => $query->where('is_accepting_queue', true))
            ->distinct('room_name')
            ->count('room_name');

        $durationPerGroup = (int) ($category->categoryScheduleSetting?->duration_minutes ?? 0);

        if ($totalDatesConfigured === 0 || $rooms === 0 || $durationPerGroup === 0) {
            return ['configured' => false];
        }

        // Only ongoing and upcoming days can still absorb groups
        // (PresentationDate::isOpenForScheduling() — not COMPLETED/CANCELLED,
        // and not a day whose window passed without ever starting). Rooms and
        // slots come from those days' own rooms only (user-directed
        // 2026-09-13): a room still attached to a finished day adds nothing.
        $remainingDates = $category->presentationDates()
            ->with('eventDateStatus', 'presentationDateRooms.roomUseStatus', 'presentationDateRooms.scheduleBreaks')
            ->chronological()
            ->get()
            ->filter(fn ($date) => $date->isOpenForScheduling())
            ->values();

        $days = $remainingDates->count();
        $completedDays = $totalDatesConfigured - $days;

        $openRooms = $remainingDates->flatMap(fn ($date) => $date->presentationDateRooms
            ->filter(fn ($room) => $room->roomUseStatus?->is_accepting_queue)
            ->map(fn ($room) => (object) ['date' => $date, 'room' => $room]));

        $rooms = $openRooms->pluck('room.room_name')->unique()->count();

        $now = now();
        $firstDate = $remainingDates->first();
        $minutesPerDay = $firstDate
            ? $this->remainingMinutes($firstDate, $firstDate->event_start_time, $firstDate->event_end_time, $now)
            : 0;

        // Each open room-day contributes its own slots — its own room window
        // when one is set, otherwise its day's window (same rule as
        // analyzeRoomDay()). Total capacity is their exact sum, and
        // Slots/Room/Day is the per-room-day average, which equals every
        // room-day's slot count whenever they're all sized the same.
        $roomDaySlots = $openRooms->map(function ($openRoom) use ($durationPerGroup, $now) {
            $minutes = $this->remainingMinutes(
                $openRoom->date,
                $openRoom->room->room_start_time ?? $openRoom->date->event_start_time,
                $openRoom->room->room_end_time ?? $openRoom->date->event_end_time,
                $now,
                $openRoom->room,
            );

            return $minutes > 0 ? intdiv($minutes, $durationPerGroup) : 0;
        });

        $totalCapacity = (int) $roomDaySlots->sum();
        $slotsPerRoomPerDay = $roomDaySlots->isNotEmpty() ? intdiv($totalCapacity, $roomDaySlots->count()) : 0;

        $registeredGroups = $category->researchGroups()->count();

        // User-directed 2026-10-13. Capacity is measured against the groups
        // still to present (To Present); To Be Scheduled is the part of them
        // that has no time right now — the same "to be scheduled" rule every
        // other screen uses (AttemptSchedule::isAwaitingReschedule()).
        ['toPresent' => $toPresent, 'toBeScheduled' => $toBeScheduled] = $this->groupsToPresent($category);

        $openSlots = $totalCapacity - $toPresent;

        $message = $openSlots >= 0
            ? sprintf('Enough capacity — %d open %s left.', $openSlots, $openSlots === 1 ? 'slot' : 'slots')
            : sprintf('Short by %d %s — add a room or another presentation date.', -$openSlots, -$openSlots === 1 ? 'slot' : 'slots');

        if ($toBeScheduled > 0) {
            $message .= sprintf(' %d %s to be scheduled — add a date or room.', $toBeScheduled, $toBeScheduled === 1 ? 'group' : 'groups');
        }

        return [
            'configured' => true,
            'total_days' => $totalDatesConfigured,
            'completed_days' => $completedDays,
            'days' => $days,
            'rooms' => $rooms,
            'minutes_per_day' => $minutesPerDay,
            'duration_per_group' => $durationPerGroup,
            'slots_per_room_per_day' => $slotsPerRoomPerDay,
            'total_capacity' => $totalCapacity,
            'registered_groups' => $registeredGroups,
            'groups_to_present' => $toPresent,
            'groups_to_be_scheduled' => $toBeScheduled,
            'remaining' => $openSlots,
            'status' => $openSlots >= 0 ? 'enough' : 'not_enough',
            'message' => $message,
        ];
    }

    /**
     * Read off each group's latest attempt. To present: no attempt yet, a
     * latest attempt that hasn't finished, or a finished one whose outcome
     * needs another attempt (a re-defense not reinserted yet). To be
     * scheduled: to present with no time — never queued, or queued with no
     * slot, or parked on a day that can no longer run. A pending re-defense
     * is not "to be scheduled": it waits on the Admin's Reinsert, not on a
     * date or room.
     *
     * @return array{toPresent: int, toBeScheduled: int}
     */
    private function groupsToPresent(PresentationCategory $category): array
    {
        $groups = $category->researchGroups()->with([
            'presentationAttempts.presentationStatus',
            'presentationAttempts.finalOutcome',
            'presentationAttempts.attemptSchedule.queueEntry',
            'presentationAttempts.attemptSchedule.presentationDateRoom.presentationDate.eventDateStatus',
        ])->get();

        $toPresent = 0;
        $toBeScheduled = 0;

        foreach ($groups as $group) {
            $latest = $group->presentationAttempts->sortBy('attempt_number')->last();

            if ($latest === null) {
                $toPresent++;
                $toBeScheduled++;

                continue;
            }

            if (! $latest->presentationStatus?->is_terminal) {
                $toPresent++;

                $schedule = $latest->attemptSchedule;
                if ($schedule === null || $schedule->isAwaitingReschedule($latest)) {
                    $toBeScheduled++;
                }

                continue;
            }

            if ($latest->presentationStatus->code === 'COMPLETED' && $latest->finalOutcome?->requires_new_attempt) {
                $toPresent++;
            }
        }

        return ['toPresent' => $toPresent, 'toBeScheduled' => $toBeScheduled];
    }

    /**
     * Minutes still usable in one day's window, counted from whichever is
     * later: the window's planned start or right now (user-directed
     * 2026-09-13). An upcoming day keeps its full planned window; on the
     * day itself time already gone — a late start, or slots already used
     * by groups that have presented (who no longer count as awaiting a
     * slot) — stops counting as capacity. Past the planned end it's 0.
     * When a room is given, the time its breaks take out of that span is
     * not capacity either — nothing is scheduled on a break.
     */
    private function remainingMinutes(\App\Models\PresentationDate $date, string $startTime, string $endTime, Carbon $now, ?PresentationDateRoom $room = null): int
    {
        $day = $date->presentation_date->toDateString();
        $start = Carbon::parse("{$day} {$startTime}");
        $end = Carbon::parse("{$day} {$endTime}");

        $from = $start->greaterThan($now) ? $start : $now;

        if (! $end->greaterThan($from)) {
            return 0;
        }

        $minutes = (int) $from->diffInMinutes($end);

        return $room ? max(0, $minutes - $room->breakMinutesBetween($from, $end)) : $minutes;
    }

    /**
     * Same days×rooms×slots formula as analyze(), scoped to a single room on
     * a single day — used by the Reinsert modal so an admin can see whether
     * the room they're reinserting into still has room today before picking
     * a position. Deliberately advisory only: a "full" room never blocks
     * reinsertion, it's just a warning surfaced to the caller.
     */
    public function analyzeRoomDay(PresentationDateRoom $room): array
    {
        $room->loadMissing('presentationDate.category.categoryScheduleSetting');

        $durationPerGroup = (int) ($room->presentationDate->category->categoryScheduleSetting?->duration_minutes ?? 0);

        $startTime = $room->room_start_time ?? $room->presentationDate->event_start_time;
        $endTime = $room->room_end_time ?? $room->presentationDate->event_end_time;
        $windowStart = Carbon::parse($room->presentationDate->presentation_date->toDateString() . ' ' . $startTime);
        $windowEnd = Carbon::parse($room->presentationDate->presentation_date->toDateString() . ' ' . $endTime);
        $minutes = max(0, (int) $windowStart->diffInMinutes($windowEnd) - $room->breakMinutesBetween($windowStart, $windowEnd));

        if ($durationPerGroup === 0 || $minutes <= 0) {
            return ['configured' => false];
        }

        $slots = intdiv($minutes, $durationPerGroup);

        $used = QueueEntry::whereHas('attemptSchedule', fn ($query) => $query->where('presentation_date_room_id', $room->id))
            ->whereNull('removed_at')
            ->count();

        $remaining = $slots - $used;

        return [
            'configured' => true,
            'slots' => $slots,
            'used' => $used,
            'remaining' => $remaining,
            'status' => $remaining > 0 ? 'enough' : ($remaining === 0 ? 'exact' : 'over'),
        ];
    }
}
