<?php

namespace App\Services;

use App\Models\CategoryRoom;
use App\Models\PresentationDateRoom;
use App\Models\ResearchGroup;

/**
 * Which rooms a group may be queued in, by research track (user-directed
 * 2026-09-29). A room registered with tracks takes only groups whose leader
 * is on one of them — never anyone else, even with time to spare. A room
 * with no tracks takes every group no track room takes: groups with no
 * track, or with a track no room was given. A category with no track rooms
 * at all is unrestricted.
 *
 * The one place this rule lives. Queue generation, every automatic move
 * (carry-over, overflow, pull-forward, date removal, re-defense) and the
 * Admin's Transfer all ask accepts(); QueueAdjustmentService::
 * recalcRoomTimes() gives a group sitting in a room that no longer accepts
 * it no time, so it waits "to be scheduled" until a matching room has one.
 *
 * Rooms on a day are matched to the registry by name, as everywhere else
 * (presentation_date_rooms has no category_room_id).
 */
class TrackRouting
{
    /** @var array<int, array{rooms: array<string, array<int, string>>, all: array<int, string>}> */
    private static array $cache = [];

    public function accepts(PresentationDateRoom $room, ?ResearchGroup $group): bool
    {
        $room->loadMissing('presentationDate');
        $rules = $this->rulesFor($room->presentationDate->category_id);

        if ($rules['rooms'] === []) {
            return true;
        }

        $track = $this->trackOf($group);
        $roomTracks = $rules['rooms'][self::key($room->room_name)] ?? [];

        if ($roomTracks !== []) {
            return $track !== null && in_array($track, $roomTracks, true);
        }

        return $track === null || ! in_array($track, $rules['all'], true);
    }

    /** Whether any room in the category is limited to tracks. */
    public function isRestricted(int $categoryId): bool
    {
        return $this->rulesFor($categoryId)['rooms'] !== [];
    }

    /** The group's routing track: its leader's, normalized; null when there is none. */
    public function trackOf(?ResearchGroup $group): ?string
    {
        if (! $group) {
            return null;
        }

        $group->loadMissing('students');
        $name = $group->leader()?->research_track_name;

        return $name === null || trim($name) === '' ? null : self::key($name);
    }

    /** Drop cached rules after a category's tracks or rooms change. */
    public static function forget(?int $categoryId = null): void
    {
        if ($categoryId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$categoryId]);
        }
    }

    public static function key(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
    }

    private function rulesFor(int $categoryId): array
    {
        if (isset(self::$cache[$categoryId])) {
            return self::$cache[$categoryId];
        }

        $rooms = [];
        $all = [];

        CategoryRoom::where('category_id', $categoryId)
            ->where('is_active', true)
            ->with(['researchTracks' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->each(function (CategoryRoom $room) use (&$rooms, &$all) {
                $tracks = $room->researchTracks->map(fn ($track) => self::key($track->name))->all();

                if ($tracks !== []) {
                    $rooms[self::key($room->room_name)] = $tracks;
                    array_push($all, ...$tracks);
                }
            });

        return self::$cache[$categoryId] = ['rooms' => $rooms, 'all' => array_values(array_unique($all))];
    }
}
