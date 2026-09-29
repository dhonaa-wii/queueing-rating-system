<?php

namespace App\Support;

use App\Models\PresentationDateRoom;
use App\Models\ScheduleBreak;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;

class ScheduleBreakRows
{
    /**
     * Returns $items with each room's breaks slotted in as ScheduleBreak
     * models, so a schedule list can print a break row where the break
     * actually falls. A break lands just before the first item of its own room
     * that starts at or after the break's start — rooms never fill a break
     * (PresentationDateRoom::slotStartClearOfBreaks()), so that is exactly
     * where the timeline pauses. A break that falls after every group in its
     * room closes out that room's rows instead. An item whose $startOf is null
     * (completed history, a group awaiting a new date) neither triggers a break
     * nor counts as a schedule: a room with nothing but such items shows none.
     *
     * $items must already be in display order with each room's items together
     * (date, then room, then queue position — every schedule list is).
     *
     * @param  iterable<mixed>  $items
     * @param  Closure(mixed): ?PresentationDateRoom  $roomOf
     * @param  Closure(mixed): ?CarbonInterface  $startOf
     */
    public static function interleave(iterable $items, Closure $roomOf, Closure $startOf): Collection
    {
        $result = collect();
        $placed = [];
        $scheduledRoom = false;
        $currentRoom = null;

        $flush = function () use (&$currentRoom, &$scheduledRoom, &$placed, $result) {
            if ($currentRoom && $scheduledRoom) {
                foreach ($currentRoom->scheduleBreaks->sortBy('planned_start_at') as $break) {
                    if (! isset($placed[$break->id])) {
                        $placed[$break->id] = true;
                        $result->push($break);
                    }
                }
            }
        };

        foreach ($items as $item) {
            $room = $roomOf($item);
            $start = $startOf($item);

            if ($room?->id !== $currentRoom?->id) {
                $flush();
                $currentRoom = $room;
                $scheduledRoom = false;
            }

            if ($room && $start) {
                $scheduledRoom = true;

                foreach ($room->scheduleBreaks->sortBy('planned_start_at') as $break) {
                    if (isset($placed[$break->id]) || $break->planned_start_at->gt($start)) {
                        continue;
                    }

                    $placed[$break->id] = true;
                    $result->push($break);
                }
            }

            $result->push($item);
        }

        $flush();

        return $result;
    }

    public static function isBreak(mixed $item): bool
    {
        return $item instanceof ScheduleBreak;
    }
}
