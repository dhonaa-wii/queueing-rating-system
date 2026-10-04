<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryRoom;
use App\Models\PresentationCategory;
use App\Models\PresentationDateRoom;
use App\Models\RoomUseStatus;
use App\Services\QueueAdjustmentService;
use App\Services\QueueGenerationService;
use App\Services\TrackRouting;
use App\Support\CategorySetupLock;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Category-level room registry — a room is registered here once (by name)
 * and reused across every presentation date via checkbox selection, instead
 * of retyping the room name per date/bulk-form. Registering a room does not
 * place it on any date by itself — assignToDates() is how a registered room
 * shows up on a day's schedule, driven by the rooms and days currently
 * checked (user-directed 2026-09-21/25; Assign to Every Day was removed
 * 2026-10-03).
 *
 * default_panelist_count is no longer set per room here — it's always
 * inherited from the category-wide Panel Count Configuration field
 * (Project Information tab, PresentationCategory::panelist_count),
 * which also overwrites every already-placed presentation_date_rooms row
 * the moment it's saved (CategoryController::updatePanelistCountConfig()).
 */
class CategoryRoomController extends Controller
{
    public function store(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'register a room');

        $validated = $request->validate([
            'room_name' => ['required', 'string', 'max:100'],
            'track_ids' => ['nullable', 'array'],
            'track_ids.*' => ['integer'],
        ]);

        $existing = $category->categoryRooms()
            ->whereRaw('LOWER(room_name) = ?', [mb_strtolower($validated['room_name'])])
            ->first();

        if ($existing && $existing->is_active) {
            $message = "\"{$validated['room_name']}\" is already registered.";

            return $request->wantsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        if ($existing) {
            $existing->update([
                'is_active' => true,
                'default_panelist_count' => $category->panelist_count,
                'added_by' => $request->user()->id,
            ]);
            $registered = $existing;
        } else {
            $registered = CategoryRoom::create([
                'category_id' => $category->id,
                'room_name' => $validated['room_name'],
                'default_panelist_count' => $category->panelist_count,
                'is_active' => true,
                'added_by' => $request->user()->id,
            ]);
        }

        $this->syncTracks($category, $registered, $validated['track_ids'] ?? [], $request->user()->id);

        $message = "\"{$registered->room_name}\" registered.";

        // The Created modal registers rooms over fetch so it stays open.
        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'room' => [
                    'id' => $registered->id,
                    'room_name' => $registered->room_name,
                    'tracks' => $registered->researchTracks()->where('is_active', true)->pluck('name')->values(),
                ],
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Renames a registered room. Room identity in this codebase is the name
     * itself (presentation_date_rooms has no category_room_id FK — every
     * relationship is matched by room_name), so a rename cascades to every
     * presentation_date_rooms row currently carrying the old name, open or
     * closed day alike — this is a label correction, not a schedule change,
     * so it needs no isOpenForScheduling() guard the way adding/removing a
     * room from a day does.
     */
    public function update(Request $request, PresentationCategory $category, CategoryRoom $room)
    {
        abort_unless($room->category_id === $category->id, 404);

        CategorySetupLock::guard($category, 'edit a room');

        $validated = $request->validate([
            'room_name' => ['required', 'string', 'max:100'],
            'track_ids' => ['nullable', 'array'],
            'track_ids.*' => ['integer'],
        ]);

        $newName = trim($validated['room_name']);

        $duplicate = $category->categoryRooms()
            ->where('id', '!=', $room->id)
            ->where('is_active', true)
            ->whereRaw('LOWER(room_name) = ?', [mb_strtolower($newName)])
            ->exists();

        if ($duplicate) {
            return back()->with('error', "\"{$newName}\" is already registered.");
        }

        $oldName = $room->room_name;

        if (mb_strtolower($oldName) !== mb_strtolower($newName)) {
            PresentationDateRoom::whereHas('presentationDate', fn ($query) => $query->where('category_id', $category->id))
                ->whereRaw('LOWER(room_name) = ?', [mb_strtolower($oldName)])
                ->update(['room_name' => $newName]);
        }

        $renamed = $oldName !== $newName;
        $room->update(['room_name' => $newName]);

        $tracksChanged = $this->syncTracks($category, $room, $validated['track_ids'] ?? [], $request->user()->id, forceRelayout: $renamed);

        return back()->with('status', $renamed ? "Room renamed to \"{$newName}\"." : ($tracksChanged ? "\"{$newName}\" tracks updated." : 'No changes.'));
    }

    /**
     * Unregisters a room and removes it from every ongoing/upcoming day it's
     * currently on in one action (user-directed 2026-09-21 — this folds what
     * used to be two separate actions, Unregister and Remove from every day,
     * into a single Delete). A finished/cancelled day's room row is left
     * exactly as it was — that's history, not something this ever touches —
     * which is also why the confirmation in the UI is skipped entirely when
     * the room's only usage is on days like that. Blocked outright, same as
     * removing a room from a single date, while a presentation is actually
     * happening in it anywhere.
     */
    public function destroy(Request $request, PresentationCategory $category, CategoryRoom $room)
    {
        abort_unless($room->category_id === $category->id, 404);

        CategorySetupLock::guard($category, 'delete a room');

        $presenting = PresentationDateRoom::whereHas('presentationDate', fn ($query) => $query->where('category_id', $category->id))
            ->whereRaw('LOWER(room_name) = ?', [mb_strtolower($room->room_name)])
            ->whereHas('attemptSchedules.presentationAttempt.presentationStatus', fn ($query) => $query->whereIn('code', ['ONGOING', 'PAUSED']))
            ->with('attemptSchedules.presentationAttempt.researchGroup')
            ->first();

        if ($presenting) {
            $group = $presenting->attemptSchedules
                ->first(fn ($schedule) => in_array($schedule->presentationAttempt?->presentationStatus?->code, ['ONGOING', 'PAUSED'], true));

            throw ValidationException::withMessages([
                'room' => "Cannot delete \"{$room->room_name}\" — {$group->presentationAttempt->researchGroup->group_reference} is presenting in it right now.",
            ]);
        }

        $removedStatus = RoomUseStatus::where('code', 'REMOVED')->firstOrFail();

        $rooms = PresentationDateRoom::whereHas('presentationDate', fn ($query) => $query->where('category_id', $category->id))
            ->where('room_use_status_id', '!=', $removedStatus->id)
            ->whereRaw('LOWER(room_name) = ?', [mb_strtolower($room->room_name)])
            ->with('presentationDate.eventDateStatus')
            ->get();

        [$openRooms, $closedRooms] = $rooms->partition(fn ($dateRoom) => $dateRoom->presentationDate->isOpenForScheduling());

        foreach ($openRooms as $dateRoom) {
            $dateRoom->update([
                'room_use_status_id' => $removedStatus->id,
                'closed_by' => $request->user()->id,
                'closed_at' => now(),
            ]);
        }

        $room->update(['is_active' => false]);
        TrackRouting::forget($category->id);

        $category->refreshStatus();

        $message = "\"{$room->room_name}\" deleted.";

        if ($openRooms->isNotEmpty()) {
            $message .= " Removed from {$openRooms->count()} ongoing/upcoming day(s).";
        }

        if ($closedRooms->isNotEmpty()) {
            $message .= " {$closedRooms->count()} finished/past day(s) were left unchanged.";
        }

        return back()->with('status', $message);
    }

    /**
     * Places the checked registered rooms onto the checked days — the "Assign
     * to Selected Days" action (user-directed 2026-09-25: days are ticked
     * like rooms are, replacing the per-day Assign button that could only
     * take one day at a time).
     */
    public function assignToDates(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'add rooms');

        $validated = $request->validate([
            'category_room_ids' => ['required', 'array', 'min:1'],
            'category_room_ids.*' => ['integer'],
            'date_ids' => ['required', 'array', 'min:1'],
            'date_ids.*' => ['integer'],
        ]);

        $rooms = $this->selectedRooms($category, $validated['category_room_ids']);

        if ($rooms->isEmpty()) {
            return back()->with('error', 'Select at least one registered room to assign.');
        }

        $dates = $category->presentationDates()->with('eventDateStatus')
            ->whereIn('id', $validated['date_ids'])
            ->get();

        $openDates = $dates->filter(fn ($date) => $date->isOpenForScheduling());
        $closedCount = $dates->count() - $openDates->count();

        if ($openDates->isEmpty()) {
            return back()->with('error', 'The selected days can no longer take a room — pick an ongoing or upcoming day.');
        }

        $result = $this->placeRooms($rooms, $openDates, $request->user()->id);
        $message = "{$rooms->count()} room(s) assigned to {$openDates->count()} day(s): {$result['created']} added, {$result['reactivated']} reactivated, {$result['skipped']} already present.";

        if ($closedCount > 0) {
            $message .= " {$closedCount} finished/past day(s) were left unchanged.";
        }

        return back()->with('status', $message.$this->finishPlacement($category, $request->user()->id));
    }

    private function selectedRooms(PresentationCategory $category, array $ids)
    {
        return $category->categoryRooms()
            ->where('is_active', true)
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * Same reactivate-if-previously-removed semantics used everywhere else
     * this file touches presentation_date_rooms: a removed row for the same
     * room name on a day comes back instead of being duplicated.
     *
     * @return array{created: int, reactivated: int, skipped: int}
     */
    private function placeRooms($rooms, $dates, int $userId): array
    {
        $activeStatus = RoomUseStatus::where('code', 'ACTIVE')->firstOrFail();
        $removedStatus = RoomUseStatus::where('code', 'REMOVED')->firstOrFail();

        $created = 0;
        $reactivated = 0;
        $skipped = 0;

        foreach ($rooms as $room) {
            foreach ($dates as $date) {
                $existing = PresentationDateRoom::where('presentation_date_id', $date->id)
                    ->whereRaw('LOWER(room_name) = ?', [mb_strtolower($room->room_name)])
                    ->first();

                if (! $existing) {
                    PresentationDateRoom::create([
                        'presentation_date_id' => $date->id,
                        'room_name' => $room->room_name,
                        'panelist_count' => $room->default_panelist_count,
                        'room_use_status_id' => $activeStatus->id,
                        'added_by' => $userId,
                        'added_at' => now(),
                    ]);
                    $created++;
                } elseif ($existing->room_use_status_id === $removedStatus->id) {
                    $existing->update([
                        'room_use_status_id' => $activeStatus->id,
                        'panelist_count' => $room->default_panelist_count ?? $existing->panelist_count,
                        'added_by' => $userId,
                        'added_at' => now(),
                        'closure_requested_by' => null,
                        'closure_requested_at' => null,
                        'closed_by' => null,
                        'closed_at' => null,
                        'removal_reason' => null,
                    ]);
                    $reactivated++;
                } else {
                    $skipped++;
                }
            }
        }

        return compact('created', 'reactivated', 'skipped');
    }

    private function finishPlacement(PresentationCategory $category, int $userId): string
    {
        $category->refreshStatus();

        $carryOver = app(QueueAdjustmentService::class)->retryStuckCarryOver($category, $userId);

        return $carryOver['moved'] > 0
            ? " {$carryOver['moved']} waiting group(s) were placed on the new schedule."
            : '';
    }

    /**
     * Limits the room to the given tracks of this category (none = no
     * limit). Re-lays the queue when that changes which rooms take which
     * groups. Returns whether the tracks changed.
     */
    private function syncTracks(PresentationCategory $category, CategoryRoom $room, array $trackIds, int $userId, bool $forceRelayout = false): bool
    {
        $valid = $category->researchTracks()->whereIn('id', $trackIds)->pluck('id')->sort()->values()->all();
        $current = $room->researchTracks()->pluck('category_research_tracks.id')->sort()->values()->all();
        $changed = $valid !== $current;

        if ($changed) {
            $room->researchTracks()->sync($valid);
        }

        if ($changed || $forceRelayout) {
            app(QueueGenerationService::class)->relayoutAfterTrackChange($category, $userId);
        }

        return $changed;
    }
}
