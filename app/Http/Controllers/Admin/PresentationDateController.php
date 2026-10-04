<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryRoom;
use App\Models\EventDateStatus;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use App\Models\RoomUseStatus;
use App\Models\ScheduleBreak;
use App\Services\QueueAdjustmentService;
use App\Services\QueueGenerationService;
use App\Support\CategorySetupLock;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PresentationDateController extends Controller
{
    /**
     * A date that's already finished (COMPLETED) or was called off
     * (CANCELLED) is locked against editing/removal — a live (ACTIVE) or
     * upcoming (PLANNED, STANDBY) date may still be freely configured
     * (user-directed 2026-08-31, supersedes the 2026-08-25 version of this
     * guard which locked ACTIVE instead of CANCELLED).
     */
    private function guardAgainstLockedDate(PresentationDate $date, string $action): void
    {
        $code = $date->eventDateStatus?->code;

        if (in_array($code, ['COMPLETED', 'CANCELLED'], true)) {
            $reason = $code === 'COMPLETED' ? 'it has already completed' : 'it was cancelled';

            throw ValidationException::withMessages([
                'event_date' => "Cannot {$action} this date — {$reason}.",
            ]);
        }
    }

    /**
     * Stricter counterpart to guardAgainstLockedDate() for anything that
     * changes what a day *contains* — its rooms and breaks. User-directed
     * 2026-09-13: Presentation Setup only ever updates ongoing and upcoming
     * days, so this uses the same isOpenForScheduling() rule Group & Panel
     * Assignment's transfer targets and Event Control's room list already
     * key off, which additionally rules out a day that simply came and went
     * unstarted. The date's own time/removal actions deliberately keep the
     * looser guard — editing an overdue date is how it gets un-stuck
     * (§2.8.5), so that path can't be locked behind being open.
     */
    private function guardDateAcceptsChanges(PresentationDate $date, string $action): void
    {
        $this->guardAgainstLockedDate($date, $action);

        if (! $date->isOpenForScheduling()) {
            throw ValidationException::withMessages([
                'event_date' => "Cannot {$action} this date — its scheduled window has already passed. Update the date first.",
            ]);
        }
    }

    /**
     * Bulk-creates one presentation date per calendar day in [start_date, end_date] —
     * the only way dates get added now (a single day is just start_date === end_date).
     * A day that already has an unfinished session is silently skipped rather than
     * erroring, so re-running this over a partially-configured range is safe. A
     * day whose sessions have all ended (or been cancelled) gets a new one.
     */
    public function storeSpan(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'add presentation dates');

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'event_start_time' => ['required', 'date_format:H:i'],
            'event_end_time' => ['required', 'date_format:H:i', 'after:event_start_time'],
            // The daily break: both times or neither, inside the daily window.
            'break_start_time' => ['nullable', 'required_with:break_end_time', 'date_format:H:i', 'after_or_equal:event_start_time', 'before:event_end_time'],
            'break_end_time' => ['nullable', 'required_with:break_start_time', 'date_format:H:i', 'after:break_start_time', 'before_or_equal:event_end_time'],
        ]);

        if (Carbon::parse($validated['start_date'])->diffInDays($validated['end_date']) > 60) {
            throw ValidationException::withMessages([
                'end_date' => 'The date span cannot exceed 60 days — add another span for the rest.',
            ]);
        }

        $plannedStatus = EventDateStatus::where('code', 'PLANNED')->firstOrFail();

        $created = 0;
        $skipped = 0;
        $createdDateIds = [];

        $period = Carbon::parse($validated['start_date'])->daysUntil($validated['end_date']);

        foreach ($period as $day) {
            // A day can hold more than one session, but never two at once: it
            // is skipped while any session already on it hasn't finished
            // (COMPLETED/CANCELLED), and gets a follow-up session once they all
            // have — e.g. the day was ended early and is being continued.
            $dayHasUnfinishedSession = PresentationDate::where('category_id', $category->id)
                ->whereDate('presentation_date', $day->toDateString())
                ->where(fn ($q) => $q
                    ->whereNull('completed_at')
                    ->orWhereHas('eventDateStatus', fn ($s) => $s->whereNotIn('code', ['COMPLETED', 'CANCELLED'])))
                ->exists();

            if ($dayHasUnfinishedSession) {
                $skipped++;

                continue;
            }

            $date = PresentationDate::create([
                'category_id' => $category->id,
                'presentation_date' => $day->toDateString(),
                'event_start_time' => $validated['event_start_time'],
                'event_end_time' => $validated['event_end_time'],
                'break_start_time' => $validated['break_start_time'] ?? null,
                'break_end_time' => $validated['break_end_time'] ?? null,
                'event_date_status_id' => $plannedStatus->id,
            ]);

            $date->refreshStatus();
            $created++;
            $createdDateIds[] = $date->id;
        }

        $category->refreshStatus();

        $carryOver = app(QueueAdjustmentService::class)->retryStuckCarryOver($category, $request->user()->id);

        $message = "Created {$created} date(s); {$skipped} already had an open schedule and were left unchanged.";
        if ($carryOver['moved'] > 0) {
            $message .= " {$carryOver['moved']} waiting group(s) were placed on the new schedule.";
        }

        // Drives the "Created" modal (schedule.blade.php) that prompts room
        // assignment for exactly this batch right after it lands — user-
        // directed 2026-09-21. One-shot flash, not persisted state: a plain
        // page reload/revisit never reopens it for the same dates.
        if (! empty($createdDateIds)) {
            session()->flash('newly_created_date_ids', $createdDateIds);
        }

        return back()->with('status', $message);
    }

    /**
     * The "No" answer to the Created modal's "Include Saturday and Sunday?"
     * prompt: removes the Saturday/Sunday dates of the batch just created.
     * Only dates that never started are touched, and each goes through the
     * same removal path as Remove Date. The remaining weekdays are flashed
     * back so the Created modal reopens on them for room assignment.
     */
    public function excludeWeekends(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'remove presentation dates');

        $validated = $request->validate([
            'date_ids' => ['required', 'array'],
            'date_ids.*' => ['integer'],
        ]);

        $dates = PresentationDate::where('category_id', $category->id)
            ->whereIn('id', $validated['date_ids'])
            ->get();

        $adjustmentService = app(QueueAdjustmentService::class);
        $removed = 0;
        $kept = [];

        foreach ($dates as $date) {
            if (! $date->presentation_date->isWeekend() || $date->activated_at !== null) {
                $kept[] = $date->id;

                continue;
            }

            $result = $adjustmentService->prepareDateForRemoval($date, $request->user()->id);
            if (! $result['ok']) {
                $kept[] = $date->id;

                continue;
            }

            $date->deleteWithChildren();
            $removed++;
        }

        $category->refreshStatus();
        $adjustmentService->retryStuckCarryOver($category->fresh(), $request->user()->id);

        if (! empty($kept)) {
            session()->flash('newly_created_date_ids', $kept);
        }

        return back()->with('status', "Excluded {$removed} Saturday/Sunday date(s).");
    }

    public function update(Request $request, PresentationCategory $category, PresentationDate $date)
    {
        abort_unless($date->category_id === $category->id, 404);

        CategorySetupLock::guard($category, 'edit this date');
        $this->guardAgainstLockedDate($date, 'edit the time for');

        $validated = $request->validate([
            'event_start_time' => ['required', 'date_format:H:i'],
            'event_end_time' => ['required', 'date_format:H:i', 'after:event_start_time'],
        ]);

        $date->update($validated);
        $date->refreshStatus();

        // Planned slots were laid out from the old start time, so a changed
        // window has to pull or push the queue rather than leave it behind.
        app(QueueAdjustmentService::class)->relayoutDate($date, $request->user()->id);

        return back()->with('status', 'Presentation date updated.');
    }

    /**
     * Removing a date can leave the category's queue referencing a room that
     * no longer exists, so this clears the FK chain deleteWithChildren()
     * needs to walk through before actually deleting anything.
     *
     * This used to go through QueueGenerationService::discardAll(), which
     * wipes and regenerates the category's ENTIRE queue — and refuses
     * outright the moment ANY attempt anywhere in the category has a
     * recorded outcome. That meant a category that had ever completed even
     * one presentation could never delete ANY date again, including an
     * unrelated upcoming one with nothing on it (user-reported 2026-09-21).
     * QueueAdjustmentService::prepareDateForRemoval() replaces that here: it
     * only looks at the date actually being removed — blocking only if that
     * specific date already holds a completed presentation, and otherwise
     * relocating whatever's still queued on it to elsewhere in the category
     * (schedule/history preserved, just re-pointed at a different room)
     * rather than touching the rest of the queue at all.
     */
    public function destroy(PresentationCategory $category, PresentationDate $date)
    {
        abort_unless($date->category_id === $category->id, 404);

        CategorySetupLock::guard($category, 'remove this date');
        $this->guardAgainstLockedDate($date, 'remove');

        $adjustmentService = app(QueueAdjustmentService::class);

        $relocateResult = $adjustmentService->prepareDateForRemoval($date, auth()->id());

        if (! $relocateResult['ok']) {
            return back()->with('error', $relocateResult['error']);
        }

        try {
            $date->deleteWithChildren();
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return back()->with('error', 'Cannot remove this date — it still has activity recorded against it.');
            }

            throw $e;
        }

        $category->refreshStatus();

        $queueService = app(QueueGenerationService::class);
        $regenerateOutcome = $queueService->autoGenerateIfEligible($category->fresh(), auth()->id());

        // Moved groups settle on the nearest day with room, and ones waiting
        // "to be scheduled" take any slot that is free.
        $adjustmentService->retryStuckCarryOver($category->fresh(), auth()->id());

        $message = 'Presentation date removed.';

        $waiting = ($relocateResult['unscheduled'] ?? 0) + ($relocateResult['unqueued'] ?? 0);

        if ($relocateResult['relocated'] > 0) {
            $groupWord = $relocateResult['relocated'] === 1 ? 'group' : 'groups';
            $message .= " {$relocateResult['relocated']} {$groupWord} queued on it were moved to another open date.";
        }

        if ($waiting > 0) {
            $message .= " {$waiting} " . ($waiting === 1 ? 'group is' : 'groups are') . ' to be scheduled — add a date or a room on their track.';
        } elseif ($relocateResult['relocated'] === 0 && ($regenerateOutcome['count'] ?? 0) > 0) {
            $message .= ' Queue generated for the remaining schedule.';
        } elseif ($regenerateOutcome['state'] === 'blocked') {
            $message .= " The queue could not be generated automatically: {$regenerateOutcome['reason']}.";
        }

        return back()->with('status', $message);
    }

    public function destroyRoom(Request $request, PresentationCategory $category, PresentationDate $date, PresentationDateRoom $room)
    {
        abort_unless($date->category_id === $category->id, 404);
        abort_unless($room->presentation_date_id === $date->id, 404);

        CategorySetupLock::guard($category, 'remove a room');
        $this->guardDateAcceptsChanges($date, 'remove a room from');

        // A group presenting in the room no longer blocks removal
        // (user-directed 2026-09-25) — the confirmation modal warns instead.
        // A completed/cancelled/passed day stays locked by the guard above.

        $removedStatus = RoomUseStatus::where('code', 'REMOVED')->firstOrFail();

        $room->update([
            'room_use_status_id' => $removedStatus->id,
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        $category->refreshStatus();

        $stranded = $room->attemptSchedules()
            ->whereHas('queueEntry', fn ($query) => $query->whereNull('removed_at'))
            ->whereHas('presentationAttempt.presentationStatus', fn ($query) => $query->where('is_terminal', false))
            ->count();

        $message = "\"{$room->room_name}\" removed from this date.";

        if ($stranded > 0) {
            $message .= " {$stranded} unfinished group(s) are still queued in it — transfer them from Group & Panel Assignment.";
        }

        return back()->with('status', $message);
    }

    /**
     * Adds a break to one room of the day, or to every room on it ("all").
     * Allowed on any ongoing or upcoming day, including one that has already
     * started and already has a generated queue (user-directed 2026-09-24):
     * the room's remaining groups are re-laid around the break at once —
     * nothing is ever scheduled on a break — and whatever no longer fits
     * before the room closes moves on to the next open room or day.
     */
    public function storeBreak(Request $request, PresentationCategory $category, PresentationDate $date, QueueAdjustmentService $queueService)
    {
        abort_unless($date->category_id === $category->id, 404);

        CategorySetupLock::guard($category, 'add a break');
        $this->guardDateAcceptsChanges($date, 'add a break to');

        $validated = $request->validate([
            'room_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:100'],
            'planned_start_at' => ['required', 'date_format:H:i'],
            'planned_end_at' => ['required', 'date_format:H:i', 'after:planned_start_at'],
        ]);

        $rooms = $date->presentationDateRooms()
            ->whereHas('roomUseStatus', fn ($query) => $query->where('code', '!=', 'REMOVED'))
            ->with('scheduleBreaks')
            ->orderBy('room_name')
            ->get();

        if ($validated['room_id'] !== 'all') {
            $rooms = $rooms->where('id', (int) $validated['room_id'])->values();
        }

        if ($rooms->isEmpty()) {
            throw ValidationException::withMessages(['break' => 'Choose a room on this date to add the break to.']);
        }

        $day = $date->presentation_date->copy();
        $start = $day->copy()->setTimeFromTimeString($validated['planned_start_at']);
        $end = $day->copy()->setTimeFromTimeString($validated['planned_end_at']);

        $added = collect();
        $skipped = collect();

        $busyRooms = $date->isRunning() ? $this->roomsBusyWithGroup($rooms) : [];

        foreach ($rooms as $room) {
            // A break later in the day is fine even while a group is on
            // stage or called; only one that starts now (or already should
            // have) would cut into that group.
            if (isset($busyRooms[$room->id]) && $start->lte(now())) {
                $skipped->push("{$room->room_name} ({$busyRooms[$room->id]} — a break can't start now)");
                continue;
            }

            $windowStart = $day->copy()->setTimeFromTimeString($room->room_start_time ?? $date->event_start_time);
            $windowEnd = $day->copy()->setTimeFromTimeString($room->room_end_time ?? $date->event_end_time);

            if ($start->lt($windowStart) || $end->gt($windowEnd)) {
                $skipped->push("{$room->room_name} (outside its hours)");
                continue;
            }

            if ($room->scheduleBreaks->contains(fn (ScheduleBreak $existing) => $start->lt($existing->planned_end_at) && $end->gt($existing->planned_start_at))) {
                $skipped->push("{$room->room_name} (overlaps another break)");
                continue;
            }

            $room->scheduleBreaks()->create([
                'name' => $validated['name'],
                'planned_start_at' => $start,
                'planned_end_at' => $end,
                'is_mandatory' => true,
            ]);

            $added->push($room);
        }

        if ($added->isEmpty()) {
            throw ValidationException::withMessages(['break' => 'The break was not added — ' . $skipped->implode(', ') . '.']);
        }

        foreach ($added as $room) {
            $room->load('scheduleBreaks');
            $queueService->relayoutAfterBreakChange($room, $request->user()->id);
        }

        $message = $added->count() === 1
            ? "Break added to {$added->first()->room_name}; its schedule was adjusted to fit."
            : "Break added to {$added->count()} rooms; their schedules were adjusted to fit.";

        if ($skipped->isNotEmpty()) {
            $message .= ' Skipped: ' . $skipped->implode(', ') . '.';
        }

        return back()->with('status', $message);
    }

    /**
     * Rooms that can't take a break starting at the current time because a
     * group is on stage (ONGOING/PAUSED) or has been called and is about to
     * start (CALLED), keyed by room id with the reason. Such a break would cut
     * into a presentation that is already underway or committed; a break set
     * for a later time is unaffected.
     *
     * @return array<int, string>
     */
    private function roomsBusyWithGroup($rooms): array
    {
        $busy = [];

        foreach ($rooms as $room) {
            $code = $room->attemptSchedules()
                ->whereHas('queueEntry', fn ($q) => $q->whereNull('removed_at'))
                ->whereHas('presentationAttempt.presentationStatus', fn ($q) => $q->whereIn('code', ['ONGOING', 'PAUSED', 'CALLED']))
                ->with('presentationAttempt.presentationStatus')
                ->get()
                ->map(fn ($schedule) => $schedule->presentationAttempt->presentationStatus->code)
                ->first();

            if ($code !== null) {
                $busy[$room->id] = $code === 'CALLED' ? 'a group has been called' : 'a presentation is ongoing';
            }
        }

        return $busy;
    }

    public function destroyBreak(Request $request, PresentationCategory $category, PresentationDate $date, PresentationDateRoom $room, ScheduleBreak $break, QueueAdjustmentService $queueService)
    {
        abort_unless($date->category_id === $category->id, 404);
        abort_unless($room->presentation_date_id === $date->id, 404);
        abort_unless($break->presentation_date_room_id === $room->id, 404);

        CategorySetupLock::guard($category, 'remove a break');
        $this->guardDateAcceptsChanges($date, 'remove a break from');

        $break->delete();

        // Groups that were pushed past the break come back to fill the time.
        $room->load('scheduleBreaks');
        $queueService->relayoutAfterBreakChange($room, $request->user()->id);

        return back()->with('status', 'Break period removed; the schedule was adjusted.');
    }
}
