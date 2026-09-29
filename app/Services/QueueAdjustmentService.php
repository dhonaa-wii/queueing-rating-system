<?php

namespace App\Services;

use App\Models\AdjustmentReason;
use App\Models\AttemptSchedule;
use App\Models\EndOfDayProcessingLog;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use App\Models\PresentationStatus;
use App\Models\QueueAdjustment;
use App\Models\QueueAdjustmentType;
use App\Models\QueueEntry;
use App\Models\RoomSession;
use App\Models\RoomSessionNotice;
use App\Models\RoomSessionStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Manual queue movement for Group & Panel Assignment (functional-spec §6.6:
 * "Transfer research groups / Change presentation rooms / Change presentation
 * dates / Reorder queues"). Reuses the same queue_adjustment_types/
 * adjustment_reasons rows QueueGenerationService's schema already defines —
 * no new tables. Queue positions are always renumbered 1..N per room rather
 * than swapped in place (matches database-schema.md's "Room changes
 * recalculate queue positions... for affected unfinished attempts" rule),
 * and planned times are recomputed the same way QueueGenerationService::
 * plan() lays out a room, walking forward from the room's start time.
 */
class QueueAdjustmentService
{
    public function __construct(
        private NotificationService $notifications,
        private PanelistConflictService $conflicts,
        private EvaluationSubmissionService $evaluationSubmissionService,
        private TrackRouting $tracks,
        private PanelistNotifier $panelistNotifier,
    ) {
    }

    public function reorder(AttemptSchedule $schedule, int $newPosition, int $reasonId, ?string $remarks, int $performedByUserId): array
    {
        $entry = $schedule->queueEntry;

        if (! $entry || $entry->removed_at !== null) {
            return $this->failure('This group is not currently in an active queue.');
        }

        if ($blocked = $this->blockedReason($schedule)) {
            return $this->failure($blocked);
        }

        $room = $schedule->presentationDateRoom;
        $oldPosition = $entry->queue_number;

        $conflicts = DB::transaction(function () use ($schedule, $entry, $room, $newPosition, $oldPosition, $reasonId, $remarks, $performedByUserId) {
            $this->releaseCalledAttempt($schedule, 'MOVED', $performedByUserId);
            $this->panelistNotifier->scheduleChanged($schedule);

            $ids = $this->activeEntryIdsForRoom($room->id)->reject(fn ($id) => $id === $entry->id)->values();
            $clamped = max($this->firstOpenPosition($ids), min($newPosition, $ids->count() + 1));
            $ids->splice($clamped - 1, 0, [$entry->id]);

            $this->applyOrder($room, $ids);

            QueueAdjustment::create([
                'queue_entry_id' => $entry->id,
                'adjustment_type_id' => QueueAdjustmentType::where('code', 'REORDER')->firstOrFail()->id,
                'old_position' => $oldPosition,
                'new_position' => $clamped,
                'reason_id' => $reasonId,
                'remarks' => $remarks,
                'approved_by' => $performedByUserId,
                'adjusted_at' => now(),
            ]);

            return $this->conflicts->conflictsInRoom($room);
        });

        if ($conflicts->isNotEmpty()) {
            $this->conflicts->syncNotifications($this->notifications);
        }

        return ['ok' => true, 'conflicts' => $conflicts];
    }

    /**
     * Covers both "Change presentation rooms" and "Change presentation
     * dates" — a date change is just a transfer to a room under a different
     * presentation_date_id, since attempt_schedules only stores
     * presentation_date_room_id.
     */
    public function transferRoom(AttemptSchedule $schedule, PresentationDateRoom $targetRoom, int $reasonId, ?string $remarks, int $performedByUserId): array
    {
        $entry = $schedule->queueEntry;

        if (! $entry || $entry->removed_at !== null) {
            return $this->failure('This group is not currently in an active queue.');
        }

        // User-directed 2026-09-12: a transfer is allowed while the day is
        // running — moving a group off a day that's overrunning, or onto
        // the room that's actually free right now, is the whole point of
        // the action once an event is live. What still can't move is a
        // group that is genuinely mid-presentation. It is also the one
        // action that stays open for a group parked on a day that can no
        // longer run — that is exactly how such a group gets out of it.
        if ($blocked = $this->blockedReason($schedule, allowClosedDay: true)) {
            return $this->failure($blocked);
        }

        $originRoom = $schedule->presentationDateRoom;

        $targetRoom->loadMissing('presentationDate.eventDateStatus', 'roomUseStatus');
        $originRoom->loadMissing('presentationDate');

        if ($targetRoom->presentationDate->category_id !== $originRoom->presentationDate->category_id) {
            return $this->failure('The target room must belong to the same category.');
        }

        if (! $targetRoom->roomUseStatus?->is_accepting_queue) {
            return $this->failure('The target room is not currently accepting queue placements.');
        }

        if ($targetRoom->hasClosedForDay()) {
            return $this->failure("\"{$targetRoom->room_name}\" has already been closed for the day — transfer to a room that is still open.");
        }

        // Ongoing (started, still running) and upcoming days both accept a
        // transfer; a finished or already-passed day does not.
        if (! $targetRoom->presentationDate->isOpenForScheduling()) {
            return $this->failure('The target presentation date has already finished — transfer to an ongoing or upcoming date instead.');
        }

        // Track rooms take only their own tracks (TrackRouting) — not even the
        // Admin can place another group there by hand.
        $schedule->loadMissing('presentationAttempt.researchGroup.students');
        if (! $this->tracks->accepts($targetRoom, $schedule->presentationAttempt->researchGroup)) {
            return $this->failure("\"{$targetRoom->room_name}\" only takes groups on its own tracks — {$schedule->presentationAttempt->researchGroup->group_reference}'s track doesn't match.");
        }

        $oldPosition = $entry->queue_number;
        $sameRoom = $targetRoom->id === $originRoom->id;

        // Refused rather than appended past the room's close time
        // (user-directed 2026-09-13). Bouncing the group straight back out
        // via spillOverflow() would look like the transfer silently failed;
        // saying so up front lets the Admin pick a day that has room.
        if (! $sameRoom && ! $this->roomHasRemainingCapacity($targetRoom)) {
            return $this->failure("\"{$targetRoom->room_name}\" on {$targetRoom->presentationDate->presentation_date->format('M j, Y')} is full for the day — its schedule has no slot left before it closes.");
        }
        $adjustmentTypeCode = $targetRoom->presentation_date_id !== $originRoom->presentation_date_id ? 'CHANGE_DATE' : 'TRANSFER_ROOM';

        if ($sameRoom) {
            return ['ok' => true, 'unchanged' => true];
        }

        $conflicts = DB::transaction(function () use ($schedule, $entry, $originRoom, $targetRoom, $oldPosition, $adjustmentTypeCode, $reasonId, $remarks, $performedByUserId) {
            $this->releaseCalledAttempt($schedule, 'TRANSFERRED', $performedByUserId, $targetRoom);
            $this->panelistNotifier->scheduleChanged($schedule);

            $schedule->update(['presentation_date_room_id' => $targetRoom->id]);

            $this->applyOrder($originRoom, $this->activeEntryIdsForRoom($originRoom->id));

            $targetIds = $this->activeEntryIdsForRoom($targetRoom->id)->reject(fn ($id) => $id === $entry->id)->values();
            $targetIds->push($entry->id);
            $this->applyOrder($targetRoom, $targetIds);

            QueueAdjustment::create([
                'queue_entry_id' => $entry->id,
                'adjustment_type_id' => QueueAdjustmentType::where('code', $adjustmentTypeCode)->firstOrFail()->id,
                'old_position' => $oldPosition,
                'new_position' => $entry->fresh()->queue_number,
                'reason_id' => $reasonId,
                'remarks' => $remarks,
                'approved_by' => $performedByUserId,
                'adjusted_at' => now(),
            ]);

            return $this->conflicts->conflictsInRoom($originRoom)->merge($this->conflicts->conflictsInRoom($targetRoom));
        });

        if ($conflicts->isNotEmpty()) {
            $this->conflicts->syncNotifications($this->notifications);
        }

        return ['ok' => true, 'conflicts' => $conflicts];
    }

    /**
     * $notifyAdmins: only a panelist's defer from the room session tablet
     * (PresentationControlService) raises GROUP_DEFERRED. An Administrator
     * deferring from Group & Panel Assignment already knows — user-directed
     * 2026-09-15: no notification for the admin's own action.
     *
     * $fromRoomSession marks the Lead's own Defer on the tablet
     * (PresentationControlService), the one caller allowed to defer a group
     * that is mid-presentation and which handles the room session/status
     * itself afterwards. An Administrator's defer is refused for a group that
     * is presenting, and releases the room's called group when it is the one
     * being deferred.
     */
    public function defer(QueueEntry $entry, int $reasonId, ?string $remarks, int $performedByUserId, bool $notifyAdmins = false, bool $fromRoomSession = false): array
    {
        $entry->loadMissing('attemptSchedule.presentationDateRoom', 'attemptSchedule.presentationAttempt.presentationStatus', 'attemptSchedule.presentationAttempt.researchGroup');

        if ($entry->removed_at !== null) {
            return $this->failure('This group has already been removed from the queue.');
        }

        if ($blocked = $this->blockedReason($entry->attemptSchedule, allowPresenting: $fromRoomSession)) {
            return $this->failure($blocked);
        }

        // User-directed 2026-09-17: once one of the group's assigned
        // panelists has actually scored it, sending it back to the queue
        // would let it be re-presented and re-evaluated on top of a real,
        // already-recorded sheet — same boundary
        // PresentationAttemptAdminActionService::delete() already refuses
        // on. This is the one place both the admin Defer action and the
        // room-session tablet's Defer/Refer-to-Admin flow go through, so
        // the rule applies everywhere defer can be triggered from.
        if ($this->evaluationSubmissionService->hasRealSubmission($entry->attemptSchedule->presentationAttempt)) {
            return $this->failure('This group already has a submitted evaluation from an assigned panelist and cannot be deferred.');
        }

        $room = $entry->attemptSchedule->presentationDateRoom;
        $oldPosition = $entry->queue_number;

        $conflicts = DB::transaction(function () use ($entry, $room, $oldPosition, $reasonId, $remarks, $performedByUserId, $notifyAdmins, $fromRoomSession) {
            if (! $fromRoomSession) {
                $this->releaseCalledAttempt($entry->attemptSchedule, 'DEFERRED', $performedByUserId);
            }

            $entry->update(['removed_at' => now()]);

            // The Lead deferring on the tablet is the panel itself.
            if (! $fromRoomSession) {
                $this->panelistNotifier->scheduleChanged($entry->attemptSchedule, 'DEFERRED');
            }

            $this->applyOrder($room, $this->activeEntryIdsForRoom($room->id));

            QueueAdjustment::create([
                'queue_entry_id' => $entry->id,
                'adjustment_type_id' => QueueAdjustmentType::where('code', 'DEFER')->firstOrFail()->id,
                'old_position' => $oldPosition,
                'new_position' => null,
                'reason_id' => $reasonId,
                'remarks' => $remarks,
                'approved_by' => $performedByUserId,
                'adjusted_at' => now(),
            ]);

            if ($notifyAdmins) {
                $group = $entry->attemptSchedule->presentationAttempt->researchGroup;
                $panelist = User::with('profile')->find($performedByUserId);
                $panelistName = trim(($panelist?->profile->first_name ?? '') . ' ' . ($panelist?->profile->last_name ?? '')) ?: ($panelist?->username ?? 'A panelist');

                $this->notifications->notifyRole(
                    'ADMIN',
                    'GROUP_DEFERRED',
                    'Group deferred from queue',
                    "{$group->group_reference} was deferred from {$room->room_name}'s queue by {$panelistName}.",
                    $entry
                );
            }

            return $this->conflicts->conflictsInRoom($room);
        });

        if ($conflicts->isNotEmpty()) {
            $this->conflicts->syncNotifications($this->notifications);
        }

        return ['ok' => true, 'conflicts' => $conflicts];
    }

    /**
     * $position is the admin's chosen 1-based slot within the room's active
     * queue — null keeps the original "always append to the end" behavior
     * (still the default from the modal). Never blocked by the room being at
     * or over its rough capacity (CapacityAnalysisService::analyzeRoomDay())
     * — that's advisory only, surfaced to the admin before they confirm.
     */
    public function reinsert(QueueEntry $entry, int $reasonId, ?string $remarks, int $performedByUserId, ?int $position = null): array
    {
        $entry->loadMissing('attemptSchedule.presentationDateRoom.presentationDate', 'attemptSchedule.presentationAttempt.presentationStatus', 'attemptSchedule.presentationAttempt.researchGroup');

        if ($entry->removed_at === null) {
            return $this->failure('This group is already in the active queue.');
        }

        // Reinsert stays available for a group deferred on a day that has
        // since ended — putting it back is the first step to transferring it
        // onto a day that can still run.
        if ($blocked = $this->blockedReason($entry->attemptSchedule, allowClosedDay: true)) {
            return $this->failure($blocked);
        }

        $room = $entry->attemptSchedule->presentationDateRoom;

        $conflicts = DB::transaction(function () use ($entry, $room, $reasonId, $remarks, $performedByUserId, $position) {
            $this->panelistNotifier->scheduleChanged($entry->attemptSchedule);
            $entry->update(['removed_at' => null]);

            // RoomQueuePreviewService (and PresentationControlService::
            // callNext(), which is built on it) only ever treats
            // SCHEDULED/QUEUED/READY_NEXT as "callable" — a DEFERRED
            // attempt (Presentation Control's Defer action) reinserted
            // here without this reset would sit back in the active
            // queue forever un-callable, since nothing else advances
            // DEFERRED to a callable status on its own.
            $attempt = $entry->attemptSchedule->presentationAttempt;
            if ($attempt->presentationStatus?->code === 'DEFERRED') {
                $attempt->update(['presentation_status_id' => PresentationStatus::where('code', 'QUEUED')->firstOrFail()->id]);
            }

            $ids = $this->activeEntryIdsForRoom($room->id)->reject(fn ($id) => $id === $entry->id)->values();

            if ($position === null) {
                $ids->push($entry->id);
            } else {
                $clamped = max($this->firstOpenPosition($ids), min($position, $ids->count() + 1));
                $ids->splice($clamped - 1, 0, [$entry->id]);
            }

            $this->applyOrder($room, $ids);

            QueueAdjustment::create([
                'queue_entry_id' => $entry->id,
                'adjustment_type_id' => QueueAdjustmentType::where('code', 'REINSERT')->firstOrFail()->id,
                'old_position' => null,
                'new_position' => $entry->fresh()->queue_number,
                'reason_id' => $reasonId,
                'remarks' => $remarks,
                'approved_by' => $performedByUserId,
                'adjusted_at' => now(),
            ]);

            $this->notifications->markRelatedRead($entry);

            return $this->conflicts->conflictsInRoom($room);
        });

        if ($conflicts->isNotEmpty()) {
            $this->conflicts->syncNotifications($this->notifications);
        }

        return ['ok' => true, 'conflicts' => $conflicts];
    }

    /**
     * End-of-day sweep, called by EventActivationService::end() once a date
     * has actually been marked COMPLETED. Two independent passes, in this
     * order (unfinished groups placed first so a subsequent deferred-group
     * requeue correctly sees them when it looks for "the last schedule in
     * the category"):
     *
     *  1. Every still-active, non-terminal group left on $date's rooms
     *     (queued but never called — end() itself already refuses to run
     *     while anything is actually in progress) is carried to the FRONT
     *     of the next configured presentation date's same-named room
     *     (falling back to that day's first accepting room if the name
     *     doesn't exist there), preserving their relative order.
     *  2. Every group still DEFERRED from one of $date's rooms is
     *     auto-reinserted at the very end of the category's queue —
     *     wherever that chronologically last still-open slot is, which may
     *     be a different room than the one it was deferred from.
     *
     * Either pass leaves an item exactly where it was (still active in a
     * now-completed room, or still deferred) if there's nowhere left in the
     * category to place it — user-directed 2026-08-22: never auto-mark
     * absent, just leave it flagged for manual handling.
     */
    public function processEndOfDay(PresentationDate $date, int $performedByUserId): array
    {
        $date->loadMissing('category');
        $category = $date->category;

        $unfinished = $this->carryOverUnfinishedToNextDay($date, $category, $performedByUserId);
        $deferred = $this->requeueDeferredToCategoryEnd($date, $category, $performedByUserId);

        // Both sweeps append without regard for the target room's close
        // time; this pushes whatever no longer fits on to the following
        // room/day so a carried-over block never overruns a day.
        $this->spillOverflow($category, $performedByUserId);

        EndOfDayProcessingLog::create([
            'presentation_date_id' => $date->id,
            'processed_at' => now(),
            'processed_by' => $performedByUserId,
            'unresolved_group_count' => $unfinished['unmoved'] + $deferred['unmoved'],
            'absent_group_count' => 0,
            'moved_to_category_end_count' => $deferred['moved'],
            'details_json' => [
                'unfinished_carried_over' => $unfinished['moved'],
                'unfinished_unresolved' => $unfinished['unmoved'],
                'deferred_requeued' => $deferred['moved'],
                'deferred_unresolved' => $deferred['unmoved'],
            ],
        ]);

        return [
            'unfinishedMoved' => $unfinished['moved'],
            'unfinishedUnresolved' => $unfinished['unmoved'],
            'deferredMoved' => $deferred['moved'],
            'deferredUnresolved' => $deferred['unmoved'],
        ];
    }

    /**
     * Re-runs the end-of-day carry-over sweep for every one of the
     * category's already-COMPLETED dates that still has an unfinished or
     * deferred entry sitting in one of its rooms — the case where
     * processEndOfDay() ran with nowhere to place them (its own "no next
     * date → leave everything exactly where it is" fallback) and a
     * date/room has since been added. Without this, a group stuck that way
     * needed a manual Group & Panel Assignment Transfer even after the
     * admin fixed the actual gap (added the next day). Reuses the same two
     * private sweep methods processEndOfDay() itself calls — they already
     * no-op cheaply when a given date has nothing stuck — so this is safe
     * to call unconditionally after any date/room addition. Called from
     * Admin\PresentationDateController::storeSpan()/storeRoomAcrossDates().
     */
    public function retryStuckCarryOver(PresentationCategory $category, int $performedByUserId): array
    {
        $moved = 0;
        $unmoved = 0;

        $completedDates = $category->presentationDates()
            ->whereNotNull('completed_at')
            ->chronological()
            ->get();

        foreach ($completedDates as $date) {
            $unfinished = $this->carryOverUnfinishedToNextDay($date, $category, $performedByUserId);
            $deferred = $this->requeueDeferredToCategoryEnd($date, $category, $performedByUserId);

            $moved += $unfinished['moved'] + $deferred['moved'];
            $unmoved += $unfinished['unmoved'] + $deferred['unmoved'];

            if ($unfinished['moved'] > 0 || $deferred['moved'] > 0) {
                EndOfDayProcessingLog::create([
                    'presentation_date_id' => $date->id,
                    'processed_at' => now(),
                    'processed_by' => $performedByUserId,
                    'unresolved_group_count' => $unfinished['unmoved'] + $deferred['unmoved'],
                    'absent_group_count' => 0,
                    'moved_to_category_end_count' => $deferred['moved'],
                    'details_json' => [
                        'retry' => true,
                        'unfinished_carried_over' => $unfinished['moved'],
                        'unfinished_unresolved' => $unfinished['unmoved'],
                        'deferred_requeued' => $deferred['moved'],
                        'deferred_unresolved' => $deferred['unmoved'],
                    ],
                ]);
            }
        }

        $pulledBack = $this->pullBackToEarlierSession($category, $performedByUserId);
        $pulledBack += $this->moveToEarliestOpenDates($category, $performedByUserId);

        // Groups taken out of the queue when a date holding the category's
        // only rooms was deleted rejoin it now that there is a room again.
        // If that left no queue at all, it is simply generated again.
        $pulledBack += $category->queueGenerationStatus() === 'Generated'
            ? (app(QueueGenerationService::class)->appendUnqueuedGroups($category, $performedByUserId)['count'] ?? 0)
            : (app(QueueGenerationService::class)->autoGenerateIfEligible($category, $performedByUserId)['count'] ?? 0);

        // Also the moment an over-full room gets somewhere to spill into:
        // this runs on every date/room addition, which is exactly when a
        // day that was overrunning its window can finally be evened out.
        $spilled = $this->spillOverflow($category, $performedByUserId);

        return ['moved' => $moved + $pulledBack + $spilled['moved'], 'unmoved' => $unmoved];
    }

    /**
     * A day ended early and its unfinished groups were carried to a later
     * day; an Administrator then adds a follow-up session that falls between
     * the two (typically the same calendar day, continued). Those groups
     * belong on that session — it is now the first open day after the one
     * they were carried from — so they are pulled back to the front of its
     * room, in their original order. User-directed 2026-09-25.
     *
     * Only groups still untouched since the end-of-day sweep qualify (their
     * newest adjustment is UNFINISHED_END_OF_DAY), so anything an
     * Administrator placed by hand stays where it was put; and only while
     * both the group's current day and the follow-up session are unstarted.
     * The follow-up must come after the completed day the group was carried
     * from, identified as the latest day completed at or before that sweep.
     * Returns how many groups were moved.
     */
    public function pullBackToEarlierSession(PresentationCategory $category, int $performedByUserId): int
    {
        $reasonId = AdjustmentReason::where('code', 'UNFINISHED_END_OF_DAY')->value('id');

        if (! $reasonId) {
            return 0;
        }

        $key = fn (PresentationDate $d) => $d->presentation_date->format('Y-m-d') . ' ' . substr((string) $d->event_start_time, 0, 8) . ' ' . str_pad((string) $d->id, 10, '0', STR_PAD_LEFT);

        $dates = $category->presentationDates()->chronological()->get();
        $completed = $dates->filter(fn ($d) => $d->completed_at !== null);
        $open = $dates->filter(fn ($d) => $d->completed_at === null && $d->activated_at === null);

        if ($completed->isEmpty() || $open->count() < 2) {
            return 0;
        }

        $entries = QueueEntry::whereNull('removed_at')
            ->whereHas('attemptSchedule.presentationDateRoom', fn ($q) => $q->whereIn('presentation_date_id', $open->pluck('id')))
            ->whereHas('attemptSchedule.presentationAttempt.presentationStatus', fn ($q) => $q->where('is_terminal', false))
            ->with(['attemptSchedule.presentationDateRoom', 'attemptSchedule.presentationAttempt.presentationStatus'])
            ->get();

        $plans = [];

        foreach ($entries as $entry) {
            $latest = QueueAdjustment::where('queue_entry_id', $entry->id)->orderByDesc('adjusted_at')->orderByDesc('id')->first();

            if (! $latest || $latest->reason_id !== $reasonId) {
                continue;
            }

            $origin = $completed
                ->filter(fn ($d) => $d->completed_at->lessThanOrEqualTo($latest->adjusted_at->copy()->addMinute()))
                ->sortBy($key)
                ->last();

            if (! $origin) {
                continue;
            }

            $current = $open->firstWhere('id', $entry->attemptSchedule->presentationDateRoom->presentation_date_id);

            $target = $open
                ->filter(fn ($d) => $key($d) > $key($origin) && $key($d) < $key($current))
                ->sortBy($key)
                ->first(fn ($d) => PresentationDateRoom::where('presentation_date_id', $d->id)
                    ->whereHas('roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
                    ->exists());

            if ($target) {
                $plans[$target->id][$entry->attemptSchedule->presentation_date_room_id][] = $entry;
            }
        }

        $moved = 0;

        foreach ($plans as $targetDateId => $byRoom) {
            foreach ($byRoom as $originRoomId => $group) {
                $originRoom = PresentationDateRoom::find($originRoomId);

                $targetRoom = PresentationDateRoom::where('presentation_date_id', $targetDateId)
                    ->where('room_name', $originRoom->room_name)
                    ->whereHas('roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
                    ->first()
                    ?? PresentationDateRoom::where('presentation_date_id', $targetDateId)
                        ->whereHas('roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
                        ->orderBy('room_name')
                        ->first();

                if (! $targetRoom) {
                    continue;
                }

                $ordered = collect($group)->sortBy('queue_number')->values();

                try {
                    DB::transaction(function () use ($ordered, $targetRoom, $performedByUserId) {
                        foreach ($ordered->reverse() as $entry) {
                            $this->relocateEntryToRoom($entry, $targetRoom, 1, 'CARRY_OVER_NEXT_DAY', 'UNFINISHED_END_OF_DAY', $performedByUserId);
                        }
                    });
                    $moved += $ordered->count();
                    // Unlike an end-of-day carry-over, the room they left still
                    // holds other groups, so close the gaps they left behind.
                    $this->renumberRoom($originRoom);
                } catch (\RuntimeException $e) {
                    // Leave them where they are.
                }
            }
        }

        return $moved;
    }

    /**
     * Groups always present on the nearest day that can take them, even when
     * that day was configured after their queue was laid out (user-directed
     * 2026-09-29: a queue sat on Oct 5 after Sep 29 was added, and only
     * deleting Oct 5 moved it). Walks the category's not-yet-started days in
     * order; each one fills its free slots with the next groups waiting on a
     * later not-yet-started day, round-robin across its rooms, each room
     * taking only groups it accepts by track (TrackRouting). A pulled group is
     * appended to the room, so the day's own groups keep their places and the
     * pulled ones keep their relative order. A group with no slot at all
     * (waiting "to be scheduled") is also pulled onto any earlier-or-same day
     * that has time.
     *
     * Never touches a day that has started (its groups are already on the
     * nearest day) or moves anyone onto one, a group already called or
     * presenting, or one an Administrator transferred by hand (its latest
     * adjustment is a Transfer or Change Date) — that placement was a choice.
     * The group keeps its attempt and its panel; the panel is told through
     * PanelistNotifier. Idempotent: once no earlier day has room, it writes
     * nothing, so it is safe on every date/room change and admin sweep.
     */
    public function moveToEarliestOpenDates(PresentationCategory $category, int $performedByUserId): int
    {
        $dates = $category->presentationDates()->chronological()->with('eventDateStatus')->get()
            ->filter(fn (PresentationDate $date) => $date->activated_at === null && $date->isOpenForScheduling())
            ->values();

        if ($dates->count() < 2) {
            return 0;
        }

        $dateIndex = $dates->pluck('id')->flip();

        $roomsByDate = PresentationDateRoom::whereIn('presentation_date_id', $dates->pluck('id'))
            ->whereHas('roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
            ->with('presentationDate')
            ->orderBy('room_name')
            ->get()
            ->groupBy('presentation_date_id');

        $manualTypeIds = QueueAdjustmentType::whereIn('code', ['TRANSFER_ROOM', 'CHANGE_DATE'])->pluck('id')->all();

        $waiting = QueueEntry::whereNull('removed_at')
            ->whereHas('attemptSchedule.presentationDateRoom', fn ($q) => $q->whereIn('presentation_date_id', $dates->pluck('id')))
            ->whereHas('attemptSchedule.presentationAttempt.presentationStatus', fn ($q) => $q
                ->where('is_terminal', false)
                ->whereNotIn('code', ['CALLED', 'ONGOING', 'PAUSED']))
            // A pending re-defense presents after everyone else (ReDefenseService).
            ->whereDoesntHave('attemptSchedule.presentationAttempt.attemptType', fn ($q) => $q->where('code', 'RE_DEFENSE'))
            ->with('attemptSchedule.presentationDateRoom', 'attemptSchedule.presentationAttempt.presentationStatus', 'attemptSchedule.presentationAttempt.researchGroup.students')
            ->get()
            ->reject(function (QueueEntry $entry) use ($manualTypeIds) {
                $latestType = QueueAdjustment::where('queue_entry_id', $entry->id)
                    ->orderByDesc('adjusted_at')->orderByDesc('id')
                    ->value('adjustment_type_id');

                return in_array($latestType, $manualTypeIds, true);
            })
            ->sortBy(fn (QueueEntry $entry) => sprintf(
                '%05d|%s|%s|%010d',
                $dateIndex[$entry->attemptSchedule->presentationDateRoom->presentation_date_id],
                $entry->attemptSchedule->planned_start_at?->format('Y-m-d H:i:s') ?? '9999',
                $entry->attemptSchedule->presentationDateRoom->room_name,
                $entry->queue_number
            ))
            ->values();

        $moved = 0;

        foreach ($dates as $date) {
            $rooms = $roomsByDate->get($date->id, collect());

            if ($rooms->isEmpty() || $waiting->isEmpty()) {
                continue;
            }

            $here = $dateIndex[$date->id];

            do {
                $placed = false;

                foreach ($rooms as $room) {
                    if (! $this->roomHasRemainingCapacity($room)) {
                        continue;
                    }

                    $index = $waiting->search(function (QueueEntry $entry) use ($room, $here, $dateIndex) {
                        $schedule = $entry->attemptSchedule;
                        $from = $dateIndex[$schedule->presentationDateRoom->presentation_date_id];

                        $waitingLater = $from > $here || ($schedule->planned_start_at === null && $from >= $here);

                        return $waitingLater
                            && $schedule->presentation_date_room_id !== $room->id
                            && $this->tracks->accepts($room, $schedule->presentationAttempt->researchGroup);
                    });

                    if ($index === false) {
                        continue;
                    }

                    $entry = $waiting->pull($index);
                    $waiting = $waiting->values();
                    $originRoom = $entry->attemptSchedule->presentationDateRoom;

                    try {
                        DB::transaction(function () use ($entry, $room, $originRoom, $performedByUserId) {
                            $this->relocateEntryToRoom($entry, $room, null, 'CARRY_OVER_NEXT_DAY', 'MOVED_TO_EARLIER_DATE', $performedByUserId);
                            // The day it left keeps other groups; close the gap.
                            $this->renumberRoom($originRoom);
                        });
                        $moved++;
                        $placed = true;
                    } catch (\RuntimeException $e) {
                        // Leave it where it is.
                    }
                }
            } while ($placed && $waiting->isNotEmpty());
        }

        if ($moved > 0) {
            $this->conflicts->syncNotifications($this->notifications);
        }

        return $moved;
    }

    /**
     * Clears a date's own queue placement so it can be safely removed,
     * without wiping the rest of the category's queue the way
     * QueueGenerationService::discardAll() does — discardAll() refuses
     * outright the moment ANY attempt anywhere in the category has a
     * recorded outcome, which blocked removing an unrelated upcoming date
     * in any category that had ever completed even one presentation
     * (user-reported 2026-09-21). This only ever looks at the date actually
     * being removed:
     *
     *  - A date that itself already holds a completed/terminal attempt
     *    can't be removed at all — that's real history, not a queue
     *    placement. Admin\PresentationDateController::destroy() also still
     *    refuses a COMPLETED/CANCELLED date up front via
     *    guardAgainstLockedDate(), so in practice this only ever fires for
     *    an upcoming or currently-active date.
     *  - Everything else on the date (never presented, whether still
     *    actively queued or already deferred) is relocated — schedule and
     *    history kept, just re-pointed at a different room, unlike
     *    discardAll()'s delete-and-regenerate — to the true end of the
     *    category's queue elsewhere, via the same
     *    lastOpenRoomInCategory()/relocateEntryToRoom() primitives
     *    requeueDeferredToCategoryEnd() already uses. Every other date's
     *    schedule is left completely untouched.
     *  - If nothing else in the category is open to relocate into, removal
     *    is refused rather than silently losing track of a registered
     *    group — the same "leave it, don't guess" posture end-of-day
     *    processing already takes when it has nowhere to carry a group to.
     */
    public function prepareDateForRemoval(PresentationDate $date, int $performedByUserId): array
    {
        $date->loadMissing('category');
        $category = $date->category;

        $roomIds = PresentationDateRoom::where('presentation_date_id', $date->id)->pluck('id');

        if ($roomIds->isEmpty()) {
            return ['ok' => true, 'relocated' => 0];
        }

        $hasTerminal = AttemptSchedule::whereIn('presentation_date_room_id', $roomIds)
            ->whereHas('presentationAttempt.presentationStatus', fn ($q) => $q->where('is_terminal', true))
            ->exists();

        if ($hasTerminal) {
            return $this->failure('This date has a completed presentation recorded on it and cannot be removed.');
        }

        $entries = QueueEntry::whereHas('attemptSchedule', fn ($q) => $q->whereIn('presentation_date_room_id', $roomIds))
            ->whereHas('attemptSchedule.presentationAttempt.presentationStatus', fn ($q) => $q->where('is_terminal', false))
            ->orderBy('queue_number')
            ->get();

        if ($entries->isEmpty()) {
            return ['ok' => true, 'relocated' => 0];
        }

        $entries->load('attemptSchedule.presentationAttempt.researchGroup.students', 'attemptSchedule.presentationAttempt.presentationStatus');

        // A group with no open room on its track left is not a reason to keep
        // the date (user-directed 2026-09-29): it waits "to be scheduled".
        // Every schedule row needs a room, so it is parked, with no time, in
        // the category's last other room — an open one first (recalcRoomTimes()
        // gives it no slot there when the room isn't on its track or is full;
        // the sweeps slot it once a room on its track has time), otherwise one
        // on a finished day, which every screen already reads as awaiting a
        // new date.
        $otherRooms = PresentationDateRoom::whereHas('presentationDate', fn ($q) => $q->where('category_id', $category->id)->where('id', '!=', $date->id))
            ->with('presentationDate.eventDateStatus', 'roomUseStatus')
            ->get()
            ->sortBy(fn (PresentationDateRoom $room) => $room->presentationDate->presentation_date->format('Y-m-d') . ' ' . $room->presentationDate->event_start_time . ' ' . $room->room_name)
            ->values();

        $parking = $otherRooms->last(fn (PresentationDateRoom $room) => $room->roomUseStatus?->is_accepting_queue && $room->presentationDate->isOpenForScheduling())
            ?? $otherRooms->last();

        $targets = $entries->mapWithKeys(fn (QueueEntry $entry) => [
            $entry->id => $this->lastOpenRoomInCategory($category, $date->id, $entry->attemptSchedule->presentationAttempt->researchGroup) ?? $parking,
        ]);

        $relocated = 0;
        $unqueued = 0;

        try {
            DB::transaction(function () use ($entries, $targets, $performedByUserId, &$relocated, &$unqueued) {
                foreach ($entries as $entry) {
                    if ($targets[$entry->id]) {
                        $this->relocateEntryToRoom($entry, $targets[$entry->id], null, 'CARRY_OVER_NEXT_DAY', 'DATE_REMOVED', $performedByUserId);
                        $relocated++;

                        continue;
                    }

                    // The date held the category's only rooms: nowhere to park a
                    // schedule row. The group goes back to registered-but-not-
                    // queued, and is queued again as soon as a date with a room
                    // exists (retryStuckCarryOver() / autoGenerateIfEligible()).
                    $result = app(PresentationAttemptAdminActionService::class)->delete($entry->attemptSchedule, $performedByUserId);

                    if (! $result['ok']) {
                        throw new \RuntimeException($result['error']);
                    }

                    $unqueued++;
                }
            });
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage());
        }

        $unscheduled = AttemptSchedule::whereIn('presentation_attempt_id', $entries->map(fn ($entry) => $entry->attemptSchedule->presentation_attempt_id))
            ->with('presentationDateRoom.presentationDate.eventDateStatus', 'presentationAttempt.presentationStatus', 'queueEntry')
            ->get()
            ->filter(fn (AttemptSchedule $schedule) => $schedule->isAwaitingReschedule())
            ->count();

        return ['ok' => true, 'relocated' => $relocated - $unscheduled, 'unscheduled' => $unscheduled, 'unqueued' => $unqueued];
    }

    private function carryOverUnfinishedToNextDay(PresentationDate $date, PresentationCategory $category, int $performedByUserId): array
    {
        $moved = 0;
        $unmoved = 0;

        // "Next" includes a follow-up session on the same calendar day (a day
        // ended early and continued): it is always created after this one
        // finished, so a higher id on the same day is later.
        $nextDate = $category->presentationDates()
            ->whereNull('completed_at')
            ->where(fn ($q) => $q
                ->where('presentation_date', '>', $date->presentation_date)
                ->orWhere(fn ($q) => $q
                    ->whereDate('presentation_date', $date->presentation_date)
                    ->where('id', '>', $date->id)))
            ->chronological()
            ->first();

        $rooms = PresentationDateRoom::where('presentation_date_id', $date->id)->get();

        foreach ($rooms as $originRoom) {
            $unfinishedEntries = QueueEntry::whereHas('attemptSchedule', fn ($q) => $q->where('presentation_date_room_id', $originRoom->id))
                ->whereNull('removed_at')
                ->whereHas('attemptSchedule.presentationAttempt.presentationStatus', fn ($q) => $q->where('is_terminal', false))
                ->orderBy('queue_number')
                ->with('attemptSchedule.presentationAttempt.presentationStatus', 'attemptSchedule.presentationAttempt.researchGroup')
                ->get();

            if ($unfinishedEntries->isEmpty()) {
                continue;
            }

            if (! $nextDate) {
                $unmoved += $unfinishedEntries->count();
                continue;
            }

            $nextRooms = PresentationDateRoom::where('presentation_date_id', $nextDate->id)
                ->whereHas('roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
                ->orderBy('room_name')
                ->get();

            // Same-named room first; otherwise the day's first room that takes
            // the group's track (TrackRouting).
            $byTarget = [];

            foreach ($unfinishedEntries as $entry) {
                $group = $entry->attemptSchedule->presentationAttempt->researchGroup;
                $target = $nextRooms->first(fn ($room) => $room->room_name === $originRoom->room_name && $this->tracks->accepts($room, $group))
                    ?? $nextRooms->first(fn ($room) => $this->tracks->accepts($room, $group));

                if (! $target) {
                    $unmoved++;
                    continue;
                }

                $byTarget[$target->id]['room'] = $target;
                $byTarget[$target->id]['entries'][] = $entry;
            }

            foreach ($byTarget as $bucket) {
                $targetRoom = $bucket['room'];
                $bucketEntries = collect($bucket['entries']);

                try {
                    DB::transaction(function () use ($bucketEntries, $targetRoom, $performedByUserId) {
                        // Reversed + always-insert-at-1 is what makes the block
                        // land in original order at the very front: inserting
                        // the last entry first pushes it to position 1, then
                        // each earlier entry displaces it back down by one.
                        foreach ($bucketEntries->reverse() as $entry) {
                            $this->relocateEntryToRoom($entry, $targetRoom, 1, 'CARRY_OVER_NEXT_DAY', 'UNFINISHED_END_OF_DAY', $performedByUserId);
                        }
                    });
                    $moved += $bucketEntries->count();
                } catch (\RuntimeException $e) {
                    $unmoved += $bucketEntries->count();
                }
            }
        }

        return ['moved' => $moved, 'unmoved' => $unmoved];
    }

    private function requeueDeferredToCategoryEnd(PresentationDate $date, PresentationCategory $category, int $performedByUserId): array
    {
        $moved = 0;
        $unmoved = 0;

        $deferredEntries = QueueEntry::whereHas('attemptSchedule.presentationDateRoom', fn ($q) => $q->where('presentation_date_id', $date->id))
            ->whereNotNull('removed_at')
            ->whereHas('attemptSchedule.presentationAttempt.presentationStatus', fn ($q) => $q->where('is_terminal', false))
            ->orderBy('removed_at')
            ->with('attemptSchedule.presentationDateRoom', 'attemptSchedule.presentationAttempt.presentationStatus', 'attemptSchedule.presentationAttempt.researchGroup')
            ->get();

        foreach ($deferredEntries as $entry) {
            $targetRoom = $this->lastOpenRoomInCategory($category, null, $entry->attemptSchedule->presentationAttempt->researchGroup);

            if (! $targetRoom) {
                $unmoved++;
                continue;
            }

            try {
                DB::transaction(function () use ($entry, $targetRoom, $performedByUserId) {
                    $this->relocateEntryToRoom($entry, $targetRoom, null, 'MOVE_TO_END', 'UNRESOLVED_DEFERRED_END_OF_DAY', $performedByUserId);
                });
                $moved++;
            } catch (\RuntimeException $e) {
                $unmoved++;
            }
        }

        return ['moved' => $moved, 'unmoved' => $unmoved];
    }

    /**
     * The room holding the chronologically last active (non-removed) queue
     * slot among the category's still-open (not yet completed) rooms — the
     * true category-wide "end of the queue," which may be a different room
     * than the one a deferred group originally sat in. Falls back to the
     * earliest still-open room (position 1 is then equivalent to "the end"
     * of an empty queue) when no open room has any active entry yet, and to
     * null when there is no open room left at all.
     *
     * $excludeDateId lets prepareDateForRemoval() search "everywhere else in
     * the category" while the date being removed is still a real, non-
     * completed row (so it would otherwise qualify as its own relocation
     * target) — every other caller leaves it null and is unaffected.
     */
    private function lastOpenRoomInCategory(PresentationCategory $category, ?int $excludeDateId = null, ?\App\Models\ResearchGroup $group = null): ?PresentationDateRoom
    {
        // Only rooms that take the group's track count (TrackRouting); with
        // no group given, every room does.
        $accepts = fn (PresentationDateRoom $room) => $group === null || $this->tracks->accepts($room, $group);

        $lastSchedule = AttemptSchedule::whereHas('presentationDateRoom.presentationDate', function ($q) use ($category, $excludeDateId) {
            $q->where('category_id', $category->id)->whereNull('completed_at');

            if ($excludeDateId !== null) {
                $q->where('id', '!=', $excludeDateId);
            }
        })
            ->whereHas('presentationDateRoom.roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
            ->whereHas('queueEntry', fn ($q) => $q->whereNull('removed_at'))
            // A group waiting "to be scheduled" holds no slot, so it doesn't
            // mark where the queue ends.
            ->whereNotNull('planned_end_at')
            ->orderByDesc('planned_end_at')
            ->with('presentationDateRoom.presentationDate')
            ->get()
            ->first(fn (AttemptSchedule $schedule) => $accepts($schedule->presentationDateRoom));

        if ($lastSchedule) {
            return $lastSchedule->presentationDateRoom;
        }

        return PresentationDateRoom::whereHas('presentationDate', function ($q) use ($category, $excludeDateId) {
            $q->where('category_id', $category->id)->whereNull('completed_at');

            if ($excludeDateId !== null) {
                $q->where('id', '!=', $excludeDateId);
            }
        })
            ->whereHas('roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
            ->with('presentationDate')
            ->get()
            ->filter($accepts)
            ->sortBy(fn ($room) => $room->presentationDate->presentation_date->format('Y-m-d') . '-' . $room->room_name)
            ->first();
    }

    /**
     * Shared move primitive for the end-of-day sweep — handles both cases
     * uniformly (auto-detected by whether $entry is currently deferred):
     * un-deferring + a callable-status reset, re-pointing the schedule at
     * $targetRoom, and splicing into that room's active order at
     * $position (null = append). The origin room is deliberately left
     * untouched — by the time this runs it only holds terminal
     * (already-decided) attempts, whose historical queue_number gaps
     * don't need recompacting.
     *
     * The status reset is checked by the attempt's own code, not by
     * $wasDeferred — carryOverUnfinishedToNextDay()'s candidates always
     * have removed_at null (so $wasDeferred is always false there) but can
     * still be CALLED (2026-08-23 correction: EventActivationService::
     * end() no longer blocks on CALLED, only genuinely-live ONGOING/
     * PAUSED — see that method's own doc comment), and CALLED isn't one
     * of RoomQueuePreviewService's callable statuses. Left uncorrected,
     * a carried-over CALLED attempt would land in the next room's queue
     * but never be callable again.
     */
    private function relocateEntryToRoom(QueueEntry $entry, PresentationDateRoom $targetRoom, ?int $position, string $adjustmentTypeCode, string $reasonCode, int $performedByUserId): void
    {
        $schedule = $entry->attemptSchedule;
        $oldPosition = $entry->queue_number;
        $wasDeferred = $entry->removed_at !== null;

        $this->panelistNotifier->scheduleChanged($schedule);

        if ($wasDeferred) {
            $entry->update(['removed_at' => null]);
        }

        $attempt = $schedule->presentationAttempt;
        if (in_array($attempt->presentationStatus?->code, ['DEFERRED', 'CALLED'], true)) {
            $attempt->update(['presentation_status_id' => PresentationStatus::where('code', 'QUEUED')->firstOrFail()->id]);
        }

        $schedule->update(['presentation_date_room_id' => $targetRoom->id]);

        $ids = $this->activeEntryIdsForRoom($targetRoom->id)->reject(fn ($id) => $id === $entry->id)->values();

        if ($position === null) {
            $ids->push($entry->id);
        } else {
            $clamped = max($this->firstOpenPosition($ids), min($position, $ids->count() + 1));
            $ids->splice($clamped - 1, 0, [$entry->id]);
        }

        $this->applyOrder($targetRoom, $ids);

        QueueAdjustment::create([
            'queue_entry_id' => $entry->id,
            'adjustment_type_id' => QueueAdjustmentType::where('code', $adjustmentTypeCode)->firstOrFail()->id,
            'old_position' => $oldPosition,
            'new_position' => $entry->fresh()->queue_number,
            'reason_id' => AdjustmentReason::where('code', $reasonCode)->firstOrFail()->id,
            'remarks' => null,
            'approved_by' => $performedByUserId,
            'adjusted_at' => now(),
        ]);

        if ($wasDeferred) {
            $this->notifications->markRelatedRead($entry);
        }

        // End-of-day carry-over has no admin present to show an alert to —
        // same allow-then-notify posture as the four interactive actions
        // above, just without a session-flashed modal.
        if ($this->conflicts->conflictsInRoom($targetRoom)->isNotEmpty()) {
            $this->conflicts->syncNotifications($this->notifications);
        }
    }

    /**
     * The one rule for when an Administrator may change a group's queue
     * placement (user-directed 2026-09-24). A group is changeable while its
     * day is ongoing or upcoming, whether or not the event has started: a
     * scheduled or merely-called group can be moved, transferred, deferred or
     * deleted at any time. What can never be changed is a group that has a
     * recorded outcome, one that is presenting right now (ONGOING/PAUSED —
     * the panel is evaluating it), or one parked on a day that can no longer
     * run (its only way out is a transfer, hence $allowClosedDay).
     *
     * $allowPresenting is for the Lead's own Defer on the room-session tablet,
     * the single legitimate way to end a presentation that is in progress.
     */
    private function blockedReason(AttemptSchedule $schedule, bool $allowClosedDay = false, bool $allowPresenting = false): ?string
    {
        $schedule->loadMissing('presentationAttempt.presentationStatus', 'presentationAttempt.researchGroup', 'presentationDateRoom.presentationDate.eventDateStatus');

        $reference = $schedule->presentationAttempt->researchGroup->group_reference;

        if ($schedule->presentationAttempt->presentationStatus?->is_terminal) {
            return "{$reference} already has a recorded outcome and can no longer be changed.";
        }

        if (! $allowPresenting && in_array($schedule->presentationAttempt->presentationStatus?->code, ['ONGOING', 'PAUSED'], true)) {
            return "{$reference} is presenting right now and can't be changed — wait until it is completed or deferred.";
        }

        if (! $allowClosedDay && ! $schedule->presentationDateRoom->presentationDate->isOpenForScheduling()) {
            return "{$reference} is on a presentation date that is over — only ongoing and upcoming schedules can be changed. Transfer it to an open date first.";
        }

        return null;
    }

    /**
     * Public form of blockedReason() for callers outside this service (Edit
     * Group, Delete) that apply the same rule — null when the Administrator
     * may change this group's schedule.
     */
    public function adminBlockedReason(AttemptSchedule $schedule): ?string
    {
        return $this->blockedReason($schedule);
    }

    /**
     * When an Administrator moves, transfers, defers or deletes the group a
     * room's panel has just called, that call is void: the room session lets
     * go of it (so the Lead can call the next group), the group goes back to
     * a callable status, and a notice naming the Administrator is left for the
     * room session's tablets to show until the next group is called
     * (user-directed 2026-09-24). A group that is merely scheduled, or one
     * that is already presenting, is not a called group — nothing to release.
     *
     * $resetStatus is false for delete, where the attempt itself is about to
     * disappear.
     */
    public function releaseCalledAttempt(AttemptSchedule $schedule, string $action, int $performedByUserId, ?PresentationDateRoom $targetRoom = null, bool $resetStatus = true): void
    {
        $schedule->loadMissing('presentationAttempt.presentationStatus', 'presentationAttempt.researchGroup');
        $attempt = $schedule->presentationAttempt;

        if ($attempt->presentationStatus?->code !== 'CALLED') {
            return;
        }

        $session = RoomSession::where('current_attempt_id', $attempt->id)->first();

        if (! $session) {
            return;
        }

        $session->loadMissing('roomSessionStatus');

        $session->update([
            'current_attempt_id' => null,
            'room_session_status_id' => $session->roomSessionStatus?->code === 'ACTIVE'
                ? RoomSessionStatus::where('code', 'WAITING')->firstOrFail()->id
                : $session->room_session_status_id,
        ]);

        if ($resetStatus) {
            $attempt->update(['presentation_status_id' => PresentationStatus::where('code', $action === 'DEFERRED' ? 'DEFERRED' : 'QUEUED')->firstOrFail()->id]);
        }

        $admin = User::with('profile')->find($performedByUserId);
        $adminName = trim(($admin?->profile->first_name ?? '') . ' ' . ($admin?->profile->last_name ?? '')) ?: ($admin?->username ?? 'an administrator');
        $reference = $attempt->researchGroup->group_reference;

        $verb = match ($action) {
            'TRANSFERRED' => $targetRoom
                ? "transferred to {$targetRoom->room_name} (" . $targetRoom->loadMissing('presentationDate')->presentationDate->presentation_date->format('M j') . ')'
                : 'transferred',
            'DEFERRED' => 'deferred',
            'DELETED' => 'removed from the queue',
            default => 'moved',
        };

        RoomSessionNotice::create([
            'room_session_id' => $session->id,
            'action' => $action,
            'group_reference' => $reference,
            'message' => "{$reference} was {$verb} by {$adminName}. Call the next group.",
            'performed_by' => $performedByUserId,
            'created_at' => now(),
        ]);
    }

    /**
     * Renumbers a room's active queue back to a contiguous 1..N and
     * recomputes planned times — used after an attempt is removed from the
     * room by something other than reorder/transfer/defer/reinsert (e.g.
     * PresentationAttemptAdminActionService::delete()), which still needs
     * the room's remaining entries left in the same tidy state those four
     * actions already guarantee.
     */
    public function renumberRoom(PresentationDateRoom $room): void
    {
        $this->applyOrder($room, $this->activeEntryIdsForRoom($room->id));
    }

    /**
     * Re-lays a room's remaining groups around its breaks after one is added
     * or removed: every group still to present moves to fit (a group that
     * would have run into the break starts when it ends), and whatever no
     * longer fits before the room closes is spilled onto the next open room
     * or day, the same way a live-capacity overflow is. A break can be added
     * to a day that has already started — groups that have presented, and the
     * one on stage, keep their times.
     */
    public function relayoutAfterBreakChange(PresentationDateRoom $room, int $performedByUserId): void
    {
        $room->loadMissing('presentationDate.category');

        $this->renumberRoom($room);
        $this->spillOverflow($room->presentationDate->category, $performedByUserId);
        $this->conflicts->syncNotifications($this->notifications);
    }

    /**
     * Re-lays every room on a day after its start/end time was edited: an
     * earlier start pulls every group still to present forward, a later one
     * pushes them back, and whatever no longer fits before the new close time
     * spills onto the next open room or day. Groups that have presented, and
     * the one on stage, keep their times (recalcRoomTimes()).
     */
    public function relayoutDate(PresentationDate $date, int $performedByUserId): void
    {
        $date->load('category');

        $rooms = $date->presentationDateRooms()
            ->whereHas('roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
            ->get();

        foreach ($rooms as $room) {
            $this->renumberRoom($room);
        }

        $this->spillOverflow($date->category, $performedByUserId);
        $this->conflicts->syncNotifications($this->notifications);
    }

    /**
     * The lowest queue position a group may be moved or reinserted into for
     * a room — one past the last attempt in $orderedEntryIds that already
     * has a terminal outcome. A group that has presented (or was marked
     * absent/cancelled) has had its turn, so nothing may be given its number
     * or slotted ahead of it; without this floor an admin typing "1" into
     * the Reinsert modal renumbered an already-completed group down the
     * queue. User-directed 2026-09-13.
     *
     * Positional rather than raw queue_number: applyOrder() recompacts every
     * number to 1..N straight afterwards, so what matters is the index the
     * entry is spliced at, and terminal rows are not guaranteed to sit
     * contiguously at the front once groups have been moved around. Returns
     * 1 when nothing in the room has finished yet, which is the old
     * behaviour exactly.
     */
    private function firstOpenPosition(\Illuminate\Support\Collection $orderedEntryIds): int
    {
        if ($orderedEntryIds->isEmpty()) {
            return 1;
        }

        // A group presenting right now holds its slot just as a finished one
        // does — nothing may be inserted ahead of it either.
        $terminalByEntryId = QueueEntry::whereIn('id', $orderedEntryIds)
            ->with('attemptSchedule.presentationAttempt.presentationStatus')
            ->get()
            ->mapWithKeys(fn (QueueEntry $entry) => [
                $entry->id => $this->holdsItsSlot($entry->attemptSchedule?->presentationAttempt?->presentationStatus),
            ]);

        $lastTerminalIndex = -1;

        foreach ($orderedEntryIds->values() as $index => $entryId) {
            if ($terminalByEntryId[$entryId] ?? false) {
                $lastTerminalIndex = $index;
            }
        }

        return $lastTerminalIndex + 2;
    }

    /** Finished (terminal) or on stage right now (ONGOING/PAUSED): its time is fixed. */
    private function holdsItsSlot(?PresentationStatus $status): bool
    {
        return (bool) ($status?->is_terminal || in_array($status?->code, ['ONGOING', 'PAUSED'], true));
    }

    private function activeEntryIdsForRoom(int $roomId): \Illuminate\Support\Collection
    {
        return QueueEntry::whereHas('attemptSchedule', fn ($q) => $q->where('presentation_date_room_id', $roomId))
            ->whereNull('removed_at')
            ->orderBy('queue_number')
            ->pluck('id');
    }

    private function applyOrder(PresentationDateRoom $room, \Illuminate\Support\Collection $orderedEntryIds): void
    {
        $orderedEntryIds = $this->reDefenseLast($orderedEntryIds);

        $entries = QueueEntry::whereIn('id', $orderedEntryIds)->get()->keyBy('id');

        foreach ($orderedEntryIds->values() as $index => $entryId) {
            $entries[$entryId]->update(['queue_number' => $index + 1]);
        }

        $this->recalcRoomTimes($room);
    }

    /**
     * A group on a re-defense presents after everyone else in its room
     * (user-directed 2026-09-16: "it needs to finish all groups to present
     * first before redefense group"). Enforced here, in the one place every
     * path that changes a room's order passes through — reorder, transfer,
     * defer, reinsert, end-of-day carry-over, overflow spill and
     * ReDefenseService's own insert all call applyOrder() — rather than
     * being re-applied per caller, where the automatic ones (a deferred
     * group requeued to the end of the category's queue, an overflow spill)
     * would quietly append themselves behind the re-defense and break it.
     *
     * A stable partition: the relative order of everything else is kept
     * exactly as the caller spliced it, and several re-defense attempts in
     * one room keep their own order among themselves. Only a *pending*
     * re-defense moves — one that has already presented is terminal and
     * never moves at all, which is the same rule firstOpenPosition()
     * protects at the front of the queue.
     */
    private function reDefenseLast(\Illuminate\Support\Collection $orderedEntryIds): \Illuminate\Support\Collection
    {
        if ($orderedEntryIds->count() < 2) {
            return $orderedEntryIds;
        }

        $pendingReDefenseIds = QueueEntry::whereIn('id', $orderedEntryIds)
            ->whereHas('attemptSchedule.presentationAttempt.attemptType', fn ($q) => $q->where('code', 'RE_DEFENSE'))
            ->whereHas('attemptSchedule.presentationAttempt.presentationStatus', fn ($q) => $q->where('is_terminal', false))
            ->pluck('id')
            ->flip();

        if ($pendingReDefenseIds->isEmpty()) {
            return $orderedEntryIds;
        }

        return $orderedEntryIds->reject(fn ($id) => $pendingReDefenseIds->has($id))
            ->values()
            ->concat($orderedEntryIds->filter(fn ($id) => $pendingReDefenseIds->has($id))->values())
            ->values();
    }


    /**
     * A room's configured close time as a real datetime — the same
     * room_end_time-or-event_end_time fallback QueueGenerationService::
     * plan() uses to decide a room's capacity at generation.
     */
    private function roomWindowEnd(PresentationDateRoom $room): \Illuminate\Support\Carbon
    {
        $room->loadMissing('presentationDate');

        return $room->presentationDate->presentation_date->copy()
            ->setTimeFromTimeString($room->room_end_time ?? $room->presentationDate->event_end_time);
    }

    /**
     * Whether one more group would still finish inside the room's window.
     * User-directed 2026-09-13: nothing is scheduled past a day's close
     * time — plan() has always enforced this at generation, but every path
     * that puts a group into a room afterwards (end-of-day carry-over,
     * requeue-deferred, a manual transfer) appended without checking, which
     * is how a 10:47-17:46 day ended up with groups running to 21:57.
     */
    public function roomHasRemainingCapacity(PresentationDateRoom $room): bool
    {
        $room->loadMissing('presentationDate.category.categoryScheduleSetting');

        $setting = $room->presentationDate->category->categoryScheduleSetting;
        $durationMinutes = (int) ($setting?->duration_minutes ?? 0);

        if ($durationMinutes <= 0) {
            return false;
        }

        // A room that already has groups waiting for a slot is full by
        // definition — they would have been laid out otherwise.
        $hasUnslotted = AttemptSchedule::where('presentation_date_room_id', $room->id)
            ->whereNull('planned_start_at')
            ->whereHas('queueEntry', fn ($q) => $q->whereNull('removed_at'))
            ->whereHas('presentationAttempt.presentationStatus', fn ($q) => $q
                ->where('is_terminal', false)
                ->whereNotIn('code', ['CALLED', 'ONGOING', 'PAUSED']))
            ->exists();

        if ($hasUnslotted) {
            return false;
        }

        $lastEnd = AttemptSchedule::where('presentation_date_room_id', $room->id)
            ->whereHas('queueEntry', fn ($q) => $q->whereNull('removed_at'))
            ->orderByDesc('planned_end_at')
            ->value('planned_end_at');

        $nextStart = $lastEnd
            ? \Illuminate\Support\Carbon::parse($lastEnd)->copy()
            : $room->presentationDate->presentation_date->copy()
                ->setTimeFromTimeString($room->room_start_time ?? $room->presentationDate->event_start_time);

        // The next slot can't sit on a break, so a break just before the
        // close time can be what makes the room full.
        $room->load('scheduleBreaks');
        $nextStart = $room->slotStartClearOfBreaks($nextStart, $durationMinutes);

        return $nextStart->copy()->addMinutes($durationMinutes)->lte($this->roomWindowEnd($room));
    }

    /**
     * Active, not-yet-decided entries whose recomputed slot runs past their
     * room's close time. Terminal rows are never overflow — they already
     * happened, whenever that was.
     */
    private function overflowEntries(PresentationDateRoom $room): \Illuminate\Support\Collection
    {
        $windowEnd = $this->roomWindowEnd($room);

        return QueueEntry::whereHas('attemptSchedule', fn ($q) => $q->where('presentation_date_room_id', $room->id))
            ->whereNull('removed_at')
            ->whereHas('attemptSchedule.presentationAttempt.presentationStatus', fn ($q) => $q
                ->where('is_terminal', false)
                // A group actually on stage is never "overflow", however
                // far past the window the day has run — same ONGOING/PAUSED
                // exclusion transferRoom() and EventActivationService::end()
                // already apply before moving anything.
                ->whereNotIn('code', ['ONGOING', 'PAUSED']))
            ->orderBy('queue_number')
            ->with('attemptSchedule.presentationAttempt.presentationStatus', 'attemptSchedule.presentationAttempt.researchGroup.students')
            ->get()
            ->filter(fn ($entry) => $this->hasNoSlot($entry) || $entry->attemptSchedule->planned_end_at?->gt($windowEnd))
            ->values();
    }

    /** An active entry still to present whose schedule carries no time at all. */
    private function hasNoSlot(QueueEntry $entry): bool
    {
        $schedule = $entry->attemptSchedule;

        return $schedule->planned_start_at === null
            && $schedule->presentationAttempt->presentationStatus?->code !== 'CALLED';
    }

    /**
     * Every room the category can still schedule into, in the order
     * plan() would fill them: by date, then room name.
     */
    public function openRoomsInOrder(PresentationCategory $category): \Illuminate\Support\Collection
    {
        return PresentationDateRoom::whereHas('presentationDate', fn ($q) => $q->where('category_id', $category->id))
            ->whereHas('roomUseStatus', fn ($q) => $q->where('is_accepting_queue', true))
            ->with('presentationDate.eventDateStatus', 'roomUseStatus')
            ->get()
            ->filter(fn ($room) => $room->presentationDate->isOpenForScheduling())
            ->sortBy(fn ($room) => $room->presentationDate->presentation_date->format('Y-m-d') . '-' . $room->room_name)
            ->values();
    }

    /**
     * Pushes every group that no longer fits inside its room's window onto
     * the next open room that does — the next room on the same day first,
     * then the next day, matching plan()'s own fill order. Runs after any
     * sweep that can put groups into a room in bulk, so a day never shows
     * groups scheduled past its close time.
     *
     * A group with nowhere left to go stays exactly where it is, the same
     * fallback processEndOfDay() already uses when there's no next date —
     * EventActivationService::flagUnscheduledGroups()'s notification is
     * what tells the Admin to add a date or room.
     */
    public function spillOverflow(PresentationCategory $category, int $performedByUserId): array
    {
        $rooms = $this->openRoomsInOrder($category);
        $moved = 0;
        $unmoved = 0;

        foreach ($rooms as $index => $room) {
            $overflow = $this->overflowEntries($room);

            if ($overflow->isEmpty()) {
                continue;
            }

            // Groups without a slot may simply fit again in their own room
            // (its window was extended, a break was removed) — lay it out
            // before sending anyone elsewhere.
            if ($overflow->contains(fn (QueueEntry $entry) => $this->hasNoSlot($entry))) {
                $this->renumberRoom($room);
                $overflow = $this->overflowEntries($room);

                if ($overflow->isEmpty()) {
                    continue;
                }
            }

            foreach ($overflow as $entry) {
                // A group that ran past the close time moves on to a later
                // room; one with no slot at all takes the earliest room in the
                // category that has time for it.
                $candidates = ($this->hasNoSlot($entry)
                    ? $rooms->reject(fn ($candidate) => $candidate->id === $room->id)
                    : $rooms->slice($index + 1))
                    ->filter(fn ($candidate) => $this->tracks->accepts($candidate, $entry->attemptSchedule->presentationAttempt->researchGroup));

                $target = $candidates->first(fn ($candidate) => $this->roomHasRemainingCapacity($candidate));

                if (! $target) {
                    $unmoved++;
                    continue;
                }

                try {
                    DB::transaction(function () use ($entry, $target, $performedByUserId) {
                        $this->relocateEntryToRoom($entry, $target, null, 'CARRY_OVER_NEXT_DAY', 'LIVE_CAPACITY_ADJUSTMENT', $performedByUserId);
                    });
                    $moved++;
                } catch (\RuntimeException $e) {
                    $unmoved++;
                }
            }

            // relocateEntryToRoom() deliberately leaves the origin room
            // alone (its other callers only ever empty out a finished day),
            // but here the origin keeps live groups behind — recompact so
            // their numbering has no gaps.
            $this->renumberRoom($room);
        }

        return ['moved' => $moved, 'unmoved' => $unmoved];
    }

    private function recalcRoomTimes(PresentationDateRoom $room): void
    {
        $room->loadMissing('presentationDate.category.categoryScheduleSetting');

        $category = $room->presentationDate->category;
        $durationMinutes = (int) ($category->categoryScheduleSetting?->duration_minutes ?? 0);

        if ($durationMinutes <= 0) {
            return;
        }

        $entries = QueueEntry::whereHas('attemptSchedule', fn ($q) => $q->where('presentation_date_room_id', $room->id))
            ->whereNull('removed_at')
            ->orderBy('queue_number')
            ->with('attemptSchedule.presentationAttempt.presentationStatus', 'attemptSchedule.presentationAttempt.researchGroup.students')
            ->get();

        $cursor = $room->presentationDate->presentation_date->copy()
            ->setTimeFromTimeString($room->room_start_time ?? $room->presentationDate->event_start_time);

        // Always re-read: a break may have just been added or removed, and
        // this is what re-lays the room's remaining groups around it.
        $room->load('scheduleBreaks');

        $windowEnd = $this->roomWindowEnd($room);

        foreach ($entries as $entry) {
            $schedule = $entry->attemptSchedule;

            // A finished group, or one on stage right now, keeps the time it
            // has — only what is still to present is laid out again.
            if ($this->holdsItsSlot($schedule->presentationAttempt->presentationStatus)) {
                $cursor = $schedule->planned_end_at?->copy() ?? $cursor;
                continue;
            }

            // A room limited to other tracks never gives the group a slot
            // (TrackRouting) — it waits "to be scheduled" until a room on its
            // track has time, and takes none of this room's time meanwhile.
            if (! $this->tracks->accepts($room, $schedule->presentationAttempt->researchGroup)
                && $schedule->presentationAttempt->presentationStatus?->code !== 'CALLED') {
                $schedule->update(['planned_call_at' => null, 'planned_start_at' => null, 'planned_end_at' => null]);

                continue;
            }

            $start = $room->slotStartClearOfBreaks($cursor, $durationMinutes);
            $end = $start->copy()->addMinutes($durationMinutes);

            // Nothing is scheduled past the room's close time, and certainly
            // not onto a day that isn't configured (user-directed 2026-09-24:
            // a slot laid out this far used to run on past midnight and read
            // as the next day's date). A group that no longer fits keeps its
            // place in the queue but has no time — "to be scheduled" —
            // until spillOverflow() finds it room or a date/room is added.
            // The group the panel has already called is at the room now and is
            // never unscheduled from under them.
            if ($end->gt($windowEnd) && $schedule->presentationAttempt->presentationStatus?->code !== 'CALLED') {
                $schedule->update(['planned_call_at' => null, 'planned_start_at' => null, 'planned_end_at' => null]);
                $cursor = $windowEnd->copy();

                continue;
            }

            $schedule->update([
                'planned_call_at' => $start,
                'planned_start_at' => $start,
                'planned_end_at' => $end,
            ]);

            $cursor = $end->copy();
        }
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
