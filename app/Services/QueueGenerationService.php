<?php

namespace App\Services;

use App\Models\AttemptPanelAssignment;
use App\Models\AttemptSchedule;
use App\Models\AttemptType;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\PresentationStatus;
use App\Models\ProposedTitle;
use App\Models\QueueAdjustment;
use App\Models\QueueEntry;
use App\Models\ResearchGroup;
use App\Models\RoomSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueueGenerationService
{
    /**
     * Pure computation, no writes. Orders every registered group per the
     * category's configured strategy, then fills the category's
     * presentation dates in date order — round-robin only applies to the
     * rooms *within* the date currently being filled, so a date's rooms are
     * filled to capacity before any group spills into the next date
     * (user-directed 2026-08-06: dates were previously in the same
     * round-robin pool as rooms, which could send a group to day 2 while
     * day 1 still had open slots).
     */
    public function plan(PresentationCategory $category): array
    {
        $category->loadMissing([
            'categoryQueueSetting.queueStrategy',
            'categoryScheduleSetting',
            'presentationDates' => fn ($query) => $query->chronological(),
            'presentationDates.presentationDateRooms' => fn ($query) => $query->orderBy('room_name'),
            'presentationDates.presentationDateRooms.roomUseStatus',
            'presentationDates.presentationDateRooms.scheduleBreaks',
        ]);

        $strategyCode = $category->categoryQueueSetting?->queueStrategy?->code;

        if ($strategyCode === 'PRIORITY_BASED') {
            return $this->failure("Priority Based queueing isn't supported by Queue Generation yet — choose a different strategy in the Queue tab.");
        }

        $groups = ResearchGroup::where('category_id', $category->id)->with('students')->get();

        if ($groups->isEmpty()) {
            return $this->failure('No groups are registered in this category yet.');
        }

        $randomDrawSeed = $category->categoryQueueSetting?->randomDrawSeed();

        if ($strategyCode === 'RANDOM_DRAW' && $randomDrawSeed === null) {
            return $this->failure('The random draw has not been made yet — save the Queue tab to draw.');
        }

        $orderedGroups = $this->orderByStrategy($groups, $strategyCode, $randomDrawSeed, $category->categoryQueueSetting?->sectionOrder() ?? []);

        $dateRoomGroups = $category->presentationDates->map(
            fn ($date) => $date->presentationDateRooms
                ->filter(fn ($room) => (bool) $room->roomUseStatus?->is_accepting_queue)
                ->each(fn ($room) => $room->setRelation('presentationDate', $date))
                ->values()
        )->filter(fn ($rooms) => $rooms->isNotEmpty())->values();

        if ($dateRoomGroups->isEmpty()) {
            return $this->failure('No active rooms are configured for this category yet.');
        }

        $durationMinutes = (int) ($category->categoryScheduleSetting?->duration_minutes ?? 0);

        if ($durationMinutes <= 0) {
            return $this->failure('Presentation duration is not configured for this category yet.');
        }

        $queue = $orderedGroups->values();
        $assignments = collect();
        $routing = app(TrackRouting::class);
        $queueNumber = 1;

        foreach ($dateRoomGroups as $rooms) {
            if ($queue->isEmpty()) {
                break;
            }

            $cursors = [];
            $windowEnds = [];

            foreach ($rooms as $room) {
                $date = $room->presentationDate->presentation_date;
                $cursors[$room->id] = $date->copy()->setTimeFromTimeString($room->room_start_time ?? $room->presentationDate->event_start_time);
                $windowEnds[$room->id] = $date->copy()->setTimeFromTimeString($room->room_end_time ?? $room->presentationDate->event_end_time);
            }

            // Round-robin within this date only: one group per capable room
            // per sweep, never filling a room to capacity before the next
            // room (on the same date) gets a turn. Once every room on this
            // date is full (or the queue is empty), move to the next date.
            while ($queue->isNotEmpty()) {
                $placedThisSweep = false;

                foreach ($rooms as $room) {
                    if ($queue->isEmpty()) {
                        break;
                    }

                    // A slot never sits on a break — it starts once the break
                    // is over (PresentationDateRoom::slotStartClearOfBreaks()).
                    $slotStart = $room->slotStartClearOfBreaks($cursors[$room->id], $durationMinutes);
                    $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

                    if ($slotEnd->gt($windowEnds[$room->id])) {
                        continue;
                    }

                    // Each room takes the next group, in strategy order, that
                    // it accepts by track (TrackRouting). With no track rooms
                    // that is simply the next group. A track room's own queue
                    // therefore keeps the strategy's order: first-registered
                    // Web group first under FIFO, section sequence under
                    // Section Based.
                    $index = $queue->search(fn (ResearchGroup $candidate) => $routing->accepts($room, $candidate));

                    if ($index === false) {
                        continue;
                    }

                    $group = $queue->pull($index);
                    $queue = $queue->values();

                    $assignments->push([
                        'research_group' => $group,
                        'presentation_date_room' => $room,
                        'queue_number' => $queueNumber++,
                        'planned_start_at' => $slotStart,
                        'planned_end_at' => $slotEnd,
                    ]);

                    $cursors[$room->id] = $slotEnd->copy();
                    $placedThisSweep = true;
                }

                if (! $placedThisSweep) {
                    break;
                }
            }
        }

        // Whatever the configured days and rooms can't hold is not pushed onto
        // a day that doesn't exist (user-directed 2026-09-24): those groups are
        // registered and queued, but have no time — "to be scheduled" — until
        // a date or room is added (QueueAdjustmentService::spillOverflow()
        // slots them in as soon as there is room). The schema needs a room on
        // every schedule row, so they wait at the end of the last room's queue.
        // Capacity Analysis says how many slots are missing and Needs Attention
        // raises it — see EventActivationService::hasUnscheduledGroups().
        $unscheduled = $queue->count();

        if ($unscheduled > 0) {
            $allRooms = $dateRoomGroups->flatten(1);

            foreach ($queue as $group) {
                // Parked in the last room that takes its track, so it is
                // slotted there first once time frees up; a track no room
                // takes still needs a room for the FK and waits in the last.
                $parkedIn = $allRooms->last(fn ($room) => $routing->accepts($room, $group)) ?? $allRooms->last();

                $assignments->push([
                    'research_group' => $group,
                    'presentation_date_room' => $parkedIn,
                    'queue_number' => $queueNumber++,
                    'planned_start_at' => null,
                    'planned_end_at' => null,
                ]);
            }
        }

        return ['ok' => true, 'assignments' => $assignments, 'unscheduled' => $unscheduled];
    }

    /**
     * Persists a plan's assignments. When $wipeExisting is true, first
     * discards the category's current (non-terminal) queue and rebuilds —
     * used when the strategy changed after a queue already existed.
     */
    public function persist(PresentationCategory $category, Collection $assignments, int $performedByUserId, bool $wipeExisting): array
    {
        return DB::transaction(function () use ($category, $assignments, $performedByUserId, $wipeExisting) {
            if ($wipeExisting) {
                $wipeResult = $this->discardAll($category);

                if (! $wipeResult['ok']) {
                    return $wipeResult;
                }
            }

            $initialType = AttemptType::where('code', 'INITIAL')->firstOrFail();
            $scheduledStatus = PresentationStatus::where('code', 'SCHEDULED')->firstOrFail();
            $now = now();

            foreach ($assignments as $assignment) {
                $attempt = PresentationAttempt::create([
                    'research_group_id' => $assignment['research_group']->id,
                    'attempt_number' => 1,
                    'attempt_type_id' => $initialType->id,
                    'previous_attempt_id' => null,
                    'presentation_status_id' => $scheduledStatus->id,
                    'final_outcome_id' => null,
                    'created_by' => $performedByUserId,
                ]);

                $schedule = AttemptSchedule::create([
                    'presentation_attempt_id' => $attempt->id,
                    'presentation_date_room_id' => $assignment['presentation_date_room']->id,
                    'planned_call_at' => $assignment['planned_start_at'],
                    'planned_start_at' => $assignment['planned_start_at'],
                    'planned_end_at' => $assignment['planned_end_at'],
                    'adjusted_expected_at' => null,
                    'scheduled_by' => $performedByUserId,
                    'scheduled_at' => $now,
                    'change_reason_id' => null,
                    'remarks' => null,
                ]);

                QueueEntry::create([
                    'attempt_schedule_id' => $schedule->id,
                    'queue_number' => $assignment['queue_number'],
                    'priority_value' => null,
                    'inserted_at' => $now,
                    'removed_at' => null,
                ]);
            }

            return [
                'ok' => true,
                'count' => $assignments->count(),
                'unscheduled' => $assignments->filter(fn (array $assignment) => $assignment['planned_start_at'] === null)->count(),
            ];
        });
    }

    /**
     * Hook for Admin\CategoryController::index()/workspaceData(): fires the
     * first-ever generation the moment a category becomes eligible (matches
     * PresentationCategory::queueGenerationStatus()'s existing 'Generated'/
     * 'Not generated' binary — there is no manual trigger anywhere).
     *
     * Eligibility is setup completeness alone (Registration/Event/Queue/
     * Evaluation all configured) — it does NOT wait on registration being
     * closed (user-directed 2026-09-02: registration should be able to stay
     * open while the event is already ongoing, e.g. rolling/continuous
     * registration). A newly-registered group arriving after the queue
     * already exists is placed the same way an Admin-added group already
     * is — see syncAfterRegistrationChange() and Student\
     * RegistrationController::store().
     */
    public function autoGenerateIfEligible(PresentationCategory $category, int $performedByUserId): array
    {
        if ($category->queueGenerationStatus() === 'Generated') {
            return ['state' => 'generated'];
        }

        if ($category->setupCompletionStatus() !== 'Complete') {
            return ['state' => 'not_eligible'];
        }

        if (! $category->researchGroups()->exists()) {
            return ['state' => 'not_eligible', 'reason' => 'No groups are registered in this category yet.'];
        }

        $plan = $this->plan($category);

        if (! $plan['ok']) {
            return ['state' => 'blocked', 'reason' => $plan['error']];
        }

        $result = $this->persist($category, $plan['assignments'], $performedByUserId, wipeExisting: false);

        if (! $result['ok']) {
            return ['state' => 'blocked', 'reason' => $result['error']];
        }

        return ['state' => 'generated', 'count' => $result['count'], 'unscheduled' => $result['unscheduled']];
    }

    /**
     * Entry point for Admin\PanelAssignmentController::storeGroup(): a group
     * added manually through Group & Panel Assignment needs to end up in the
     * queue the same way a normal registration eventually does. If no queue
     * exists yet this is just the first-ever generation
     * (autoGenerateIfEligible()); if one already exists (the common case —
     * Panel Assignment only ever lists categories that already have one),
     * this reuses regenerateIfStrategyChanged()'s guarded full-rebuild so the
     * new group gets placed by the same round-robin as everyone else.
     */
    public function syncAfterRegistrationChange(PresentationCategory $category, int $performedByUserId): array
    {
        if ($category->queueGenerationStatus() === 'Generated') {
            $category->loadMissing('presentationDates');

            // Once a day has started the queue can't be rebuilt around a new
            // group, so it joins the end of the existing one instead.
            if ($category->presentationDates->contains(fn ($date) => $date->activated_at !== null)) {
                return $this->appendUnqueuedGroups($category, $performedByUserId);
            }

            return $this->regenerateIfStrategyChanged($category, $performedByUserId);
        }

        return $this->autoGenerateIfEligible($category, $performedByUserId);
    }

    /**
     * Gives every registered group that has no attempt yet a place at the end
     * of an already-running queue, without touching anyone else's placement.
     * The group takes the earliest open room that still has a free slot; when
     * none does it waits, untimed, at the end of the last open room and is
     * slotted by spillOverflow() as soon as a date or room is added.
     */
    public function appendUnqueuedGroups(PresentationCategory $category, int $performedByUserId): array
    {
        $groups = ResearchGroup::where('category_id', $category->id)
            ->whereDoesntHave('presentationAttempts')
            ->orderBy('registered_at')
            ->get();

        if ($groups->isEmpty()) {
            return ['state' => 'generated', 'count' => 0, 'unscheduled' => 0];
        }

        $adjustments = app(QueueAdjustmentService::class);
        $rooms = $adjustments->openRoomsInOrder($category);

        if ($rooms->isEmpty()) {
            return ['state' => 'blocked', 'reason' => 'no ongoing or upcoming date with a room is open for this category — add one in Presentation Setup'];
        }

        $initialType = AttemptType::where('code', 'INITIAL')->firstOrFail();
        $scheduledStatus = PresentationStatus::where('code', 'SCHEDULED')->firstOrFail();
        $unscheduled = 0;
        $routing = app(TrackRouting::class);

        foreach ($groups as $group) {
            $accepting = $rooms->filter(fn ($candidate) => $routing->accepts($candidate, $group))->values();
            $room = $accepting->first(fn ($candidate) => $adjustments->roomHasRemainingCapacity($candidate));

            if (! $room) {
                $room = $accepting->last() ?? $rooms->last();
                $unscheduled++;
            }

            DB::transaction(function () use ($group, $room, $initialType, $scheduledStatus, $performedByUserId) {
                $attempt = PresentationAttempt::create([
                    'research_group_id' => $group->id,
                    'attempt_number' => 1,
                    'attempt_type_id' => $initialType->id,
                    'previous_attempt_id' => null,
                    'presentation_status_id' => $scheduledStatus->id,
                    'final_outcome_id' => null,
                    'created_by' => $performedByUserId,
                ]);

                $schedule = AttemptSchedule::create([
                    'presentation_attempt_id' => $attempt->id,
                    'presentation_date_room_id' => $room->id,
                    'planned_call_at' => null,
                    'planned_start_at' => null,
                    'planned_end_at' => null,
                    'adjusted_expected_at' => null,
                    'scheduled_by' => $performedByUserId,
                    'scheduled_at' => now(),
                    'change_reason_id' => null,
                    'remarks' => null,
                ]);

                $lastNumber = QueueEntry::whereHas('attemptSchedule', fn ($q) => $q->where('presentation_date_room_id', $room->id))
                    ->max('queue_number') ?? 0;

                QueueEntry::create([
                    'attempt_schedule_id' => $schedule->id,
                    'queue_number' => $lastNumber + 1,
                    'priority_value' => null,
                    'inserted_at' => now(),
                    'removed_at' => null,
                ]);
            });

            // Lays the room out again so the new group gets its time (or,
            // if the room can't hold it, stays "to be scheduled").
            $adjustments->renumberRoom($room);
        }

        return ['state' => 'generated', 'count' => $groups->count(), 'unscheduled' => $unscheduled];
    }

    /**
     * Hook for Admin\CategoryController::updateQueueConfig(): if a queue
     * already exists, automatically rebuilds it to match a (possibly just
     * changed) strategy — but only while no presentation date has started
     * yet. Also reused by syncAfterRegistrationChange() above for the same
     * guarded rebuild after a new group registers (Admin-added or, since
     * 2026-09-02, public self-registration), since the guards and rebuild
     * logic are identical either way.
     *
     * Registration being open no longer blocks this (user-directed
     * 2026-09-02, see autoGenerateIfEligible()'s own note) — the guard that
     * actually matters is "has this event already started," which the
     * activated-date check below still fully covers regardless of
     * registration window state.
     */
    public function regenerateIfStrategyChanged(PresentationCategory $category, int $performedByUserId): array
    {
        if ($category->queueGenerationStatus() !== 'Generated') {
            return ['state' => 'not_applicable'];
        }

        $category->loadMissing('presentationDates');

        if ($category->presentationDates->contains(fn ($date) => $date->activated_at !== null)) {
            return ['state' => 'blocked', 'reason' => 'a presentation date for this category has already started'];
        }

        $plan = $this->plan($category);

        if (! $plan['ok']) {
            return ['state' => 'blocked', 'reason' => $plan['error']];
        }

        $result = $this->persist($category, $plan['assignments'], $performedByUserId, wipeExisting: true);

        if (! $result['ok']) {
            return ['state' => 'blocked', 'reason' => $result['error']];
        }

        return ['state' => 'regenerated', 'count' => $result['count'], 'unscheduled' => $result['unscheduled']];
    }

    /**
     * After a track is added, renamed or removed, or a room's tracks change,
     * every group has to sit in a room that takes its track (TrackRouting).
     *
     * While nothing has started and no panel has been assigned, the queue is
     * rebuilt, so each track room holds its groups in exact strategy order.
     * Otherwise nothing is wiped (a rebuild would drop the assigned panels):
     * every open room is laid out again, which leaves a group in a room that
     * no longer takes it without a time, and the overflow and pull-forward
     * sweeps move it to the earliest room on its track that has one.
     */
    public function relayoutAfterTrackChange(PresentationCategory $category, int $performedByUserId): void
    {
        TrackRouting::forget($category->id);

        if ($category->queueGenerationStatus() !== 'Generated') {
            return;
        }

        $category->load('presentationDates');

        $started = $category->presentationDates->contains(fn ($date) => $date->activated_at !== null);
        $hasPanels = AttemptPanelAssignment::whereHas('presentationAttempt.researchGroup', fn ($q) => $q->where('category_id', $category->id))->exists();

        if (! $started && ! $hasPanels && $this->regenerateIfStrategyChanged($category, $performedByUserId)['state'] === 'regenerated') {
            return;
        }

        $adjustments = app(QueueAdjustmentService::class);

        foreach ($adjustments->openRoomsInOrder($category) as $room) {
            $adjustments->renumberRoom($room);
        }

        $adjustments->spillOverflow($category, $performedByUserId);
        $adjustments->moveToEarliestOpenDates($category, $performedByUserId);
        $adjustments->spillOverflow($category, $performedByUserId);
    }

    private function orderByStrategy(Collection $groups, ?string $strategyCode, ?string $randomDrawSeed, array $sectionOrder): Collection
    {
        return match ($strategyCode) {
            'RANDOM_DRAW' => $this->orderByRandomDraw($groups, $randomDrawSeed),
            'SECTION_BASED' => $this->orderBySection($groups, $sectionOrder),
            // FIFO and any unrecognized/unset strategy fall back to registration order.
            default => $groups->sortBy('registered_at')->values(),
        };
    }

    /**
     * Random Draw: a seeded random-key sort. Each group's draw key is
     * HMAC-SHA256(seed, group id) and the queue is ordered by key.
     *
     * The previous RANDOMIZED strategy was a fresh Fisher–Yates shuffle, and
     * plan() re-runs on every regeneration — each new registration and each
     * Queue-tab save re-rolled every group's position, with no way to show
     * afterwards how the order was decided. Keys from a stored secret seed
     * fix both, while keeping the same fairness (sorting by independent
     * uniform keys yields every permutation with equal probability, just as
     * Fisher–Yates does):
     *  - the same seed always reproduces the same order, so the draw is
     *    auditable and stable across regenerations;
     *  - a group registering later gets its own independent key, so it
     *    lands at a uniformly random position and every group already drawn
     *    keeps its relative order — nobody else is reshuffled.
     * The seed is 256 bits from random_bytes() and never shown, so the order
     * can't be predicted from group ids. Ties (a SHA-256 collision) are
     * practically impossible, but fall back to the group id so the sort is
     * total.
     */
    private function orderByRandomDraw(Collection $groups, string $seed): Collection
    {
        return $groups
            ->map(fn (ResearchGroup $group) => [
                'group' => $group,
                'key' => hash_hmac('sha256', (string) $group->id, $seed),
            ])
            ->sort(fn ($a, $b) => strcmp($a['key'], $b['key']) ?: $a['group']->id <=> $b['group']->id)
            ->pluck('group')
            ->values();
    }

    /**
     * Section Based: whole sections one after another, by the leader's
     * section, each section's groups in registration order. Sections run in
     * the admin's configured Section Order (user-directed 2026-09-15); any
     * registered section left out of that order follows A–Z, and groups
     * with no section come last. An empty order is plain A–Z.
     */
    private function orderBySection(Collection $groups, array $sectionOrder): Collection
    {
        $grouped = $groups->groupBy(fn (ResearchGroup $group) => $this->leaderSection($group));
        $rank = array_flip(array_values($sectionOrder));

        $orderedKeys = $grouped->keys()->map(fn ($key) => (string) $key)->sort(function (string $a, string $b) use ($rank) {
            if ($a === '' || $b === '') {
                return ($a === '') <=> ($b === '');
            }

            if (isset($rank[$a]) || isset($rank[$b])) {
                return ($rank[$a] ?? PHP_INT_MAX) <=> ($rank[$b] ?? PHP_INT_MAX);
            }

            return strnatcasecmp($a, $b);
        })->values();

        return $orderedKeys
            ->flatMap(fn ($key) => $grouped->get($key)->sortBy('registered_at')->values())
            ->values();
    }

    private function leaderSection(ResearchGroup $group): string
    {
        return ResearchGroupRegistrationService::normalizeSection($group->leader()?->section_name);
    }

    /**
     * Discards the category's current (non-terminal) queue entirely — every
     * presentation_attempt/attempt_schedule/queue_entry for its groups, gone.
     * Public because it's also used standalone by PresentationDateController::
     * destroy() (discard the whole queue so a date-with-rooms can be deleted
     * without hitting the same FK chain, then autoGenerateIfEligible() rebuilds
     * fresh afterward) as well as internally by persist()'s $wipeExisting path.
     */
    public function discardAll(PresentationCategory $category): array
    {
        $attemptIds = PresentationAttempt::whereHas('researchGroup', fn ($query) => $query->where('category_id', $category->id))
            ->pluck('id');

        if ($attemptIds->isEmpty()) {
            return ['ok' => true];
        }

        $hasTerminal = PresentationAttempt::whereIn('id', $attemptIds)
            ->whereHas('presentationStatus', fn ($query) => $query->where('is_terminal', true))
            ->exists();

        if ($hasTerminal) {
            return ['ok' => false, 'error' => 'The queue cannot be changed because at least one attempt already has a recorded outcome.'];
        }

        DB::transaction(function () use ($attemptIds) {
            RoomSession::whereIn('current_attempt_id', $attemptIds)->update(['current_attempt_id' => null]);
            AttemptPanelAssignment::whereIn('presentation_attempt_id', $attemptIds)->delete();
            $substitutionRequestIds = \App\Models\PanelSubstitutionRequest::whereIn('presentation_attempt_id', $attemptIds)->pluck('id');
            \App\Models\Notification::where('related_type', \App\Models\PanelSubstitutionRequest::class)->whereIn('related_id', $substitutionRequestIds)->delete();
            \App\Models\PanelSubstitutionRequest::whereIn('id', $substitutionRequestIds)->delete();
            ProposedTitle::whereIn('presentation_attempt_id', $attemptIds)->update(['presentation_attempt_id' => null]);

            $scheduleIds = AttemptSchedule::whereIn('presentation_attempt_id', $attemptIds)->pluck('id');
            $queueEntryIds = QueueEntry::whereIn('attempt_schedule_id', $scheduleIds)->pluck('id');
            // queue_adjustments.queue_entry_id is a hard FK with no cascade —
            // any queue entry with adjustment history (a prior reorder,
            // defer, reinsert, etc.) made queue_entries' delete below fail
            // with a raw FK violation until this line was added. Found live
            // while verifying Presentation Control's defer/recall flow
            // against category 7, which already had real adjustment history
            // from earlier testing.
            QueueAdjustment::whereIn('queue_entry_id', $queueEntryIds)->delete();
            QueueEntry::whereIn('id', $queueEntryIds)->delete();
            AttemptSchedule::whereIn('presentation_attempt_id', $attemptIds)->delete();
            PresentationAttempt::whereIn('id', $attemptIds)->delete();
        });

        return ['ok' => true];
    }

    private function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
