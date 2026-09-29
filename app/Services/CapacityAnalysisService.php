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
 * Correspondingly, "remaining" is measured against groups still awaiting a
 * slot (no terminal presentation attempt yet), not every registered group —
 * a group that already presented on a concluded day shouldn't count against
 * the days that are still open.
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

        // A group whose presentation attempt already reached a terminal outcome
        // (COMPLETED/ABSENT/CANCELLED) no longer needs a future slot — only
        // still-registered groups without one count against remaining capacity.
        $groupsAwaitingSlot = $category->researchGroups()
            ->whereDoesntHave('presentationAttempts', fn ($query) => $query->whereHas(
                'presentationStatus',
                fn ($statusQuery) => $statusQuery->where('is_terminal', true)
            ))
            ->count();

        $remaining = $totalCapacity - $groupsAwaitingSlot;

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
            'groups_awaiting_slot' => $groupsAwaitingSlot,
            'remaining' => $remaining,
            'status' => $remaining >= 0 ? 'enough' : 'not_enough',
            'message' => $remaining >= 0
                ? "Enough capacity with {$remaining} slot(s) remaining."
                : sprintf('Short by %d slot(s) — add a room or another presentation date.', -$remaining),
        ];
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
