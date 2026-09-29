<?php

namespace App\Services;

use App\Models\AttemptPanelAssignment;
use App\Models\AttemptSchedule;
use App\Models\Notification;
use App\Models\PresentationAttempt;
use App\Models\PresentationDateRoom;
use App\Models\QueueEntry;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "can a panelist be in two places at once"
 * (functional-spec §9.7), shared by PanelAssignmentService (hard-blocks a
 * fresh assignment that would create a conflict) and QueueAdjustmentService
 * (2026-08-25: allows a queue move to save even if it creates a conflict,
 * then alerts/notifies — see conflictsInRoom()/scanAllConflicts()).
 *
 * Only a real overlap is a conflict — back-to-back bookings (one ending
 * 8:20, the next starting 8:20) are fine. A 5-minute gap used to be required
 * on top of that; it was removed 2026-09-14 (user-directed) along with the
 * queue's transition time (§2.13), since a room's groups now run back to back
 * and the gap made it impossible to seat one panel through a room's queue.
 */
class PanelistConflictService
{
    /**
     * Every live AttemptPanelAssignment for any of $panelistUserIds whose
     * own schedule overlaps $schedule's window.
     * $excludeAttemptIds is always widened to include $schedule's own
     * attempt (a schedule never "conflicts" with itself).
     */
    public function conflictingAssignments(AttemptSchedule $schedule, array $panelistUserIds, array $excludeAttemptIds = []): Collection
    {
        $panelistUserIds = array_values(array_unique($panelistUserIds));

        if (empty($panelistUserIds)) {
            return collect();
        }

        $exclude = array_values(array_unique([...$excludeAttemptIds, $schedule->presentation_attempt_id]));

        if ($schedule->planned_start_at === null || $schedule->planned_end_at === null) {
            return collect();
        }

        return AttemptPanelAssignment::whereIn('panelist_user_id', $panelistUserIds)
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->whereNotIn('presentation_attempt_id', $exclude)
            ->whereHas('presentationAttempt.attemptSchedule', function ($q) use ($schedule) {
                $q->where('planned_start_at', '<', $schedule->planned_end_at)
                    ->where('planned_end_at', '>', $schedule->planned_start_at);
            })
            // A deferred/removed queue placement no longer holds its old time
            // slot (its stale schedule row isn't recalculated), so it must
            // not count as a live conflict.
            ->whereHas('presentationAttempt.attemptSchedule.queueEntry', fn ($q) => $q->whereNull('removed_at'))
            // Nor does an attempt that has already presented (2026-09-22):
            // it keeps its queue placement, but a recorded outcome means its
            // turn is over, so the slot that placement names is not one its
            // panel is still expected at. A group carried onto a later day
            // and only completed afterwards otherwise sat on a future slot
            // forever, raising a conflict against whoever really is due
            // there (real case: CFD-2026-0008, completed Sep 17, still
            // holding Sep 22 08:10 in Room 1).
            ->whereHas('presentationAttempt.presentationStatus', fn ($q) => $q->where('is_terminal', false))
            ->with([
                'panelist.profile',
                'presentationAttempt.researchGroup.category',
                'presentationAttempt.attemptSchedule.presentationDateRoom',
            ])
            ->get();
    }

    /**
     * In-memory pairwise check for a batch of not-yet-persisted schedule
     * windows (PanelAssignmentService::findBatchOverlap()'s use case) —
     * same overlap test as the DB query above.
     */
    public function intervalsConflict($startA, $endA, $startB, $endB): bool
    {
        // A group with no slot yet ("to be scheduled") occupies no time at all.
        if ($startA === null || $endA === null || $startB === null || $endB === null) {
            return false;
        }

        return $startA->lt($endB) && $startB->lt($endA);
    }

    public function messageFor(PresentationAttempt $referenceAttempt, AttemptPanelAssignment $conflict): string
    {
        $panelistName = trim(($conflict->panelist->profile->first_name ?? '') . ' ' . ($conflict->panelist->profile->last_name ?? ''));
        $otherGroup = $conflict->presentationAttempt->researchGroup;
        $otherCategory = $otherGroup->category;
        $otherSchedule = $conflict->presentationAttempt->attemptSchedule;

        return sprintf(
            '%s is already assigned to %s (%s) at %s — overlaps with %s\'s schedule.',
            $panelistName !== '' ? $panelistName : 'A panelist',
            $otherGroup->group_reference,
            $otherCategory->name ?? 'another category',
            $otherSchedule->planned_start_at?->format('M j, g:i A'),
            $referenceAttempt->researchGroup->group_reference
        );
    }

    /**
     * Every currently-live conflict among the active (non-removed) entries
     * in one room — used after a reorder/transfer/defer/reinsert recalculates
     * that room's planned times, to report (not block) whatever it created.
     */
    public function conflictsInRoom(PresentationDateRoom $room): Collection
    {
        $entries = QueueEntry::whereHas('attemptSchedule', fn ($q) => $q->where('presentation_date_room_id', $room->id))
            ->whereNull('removed_at')
            ->with('attemptSchedule.presentationAttempt.researchGroup', 'attemptSchedule.presentationAttempt.presentationStatus')
            ->get();

        return $this->scanEntries($entries);
    }

    /**
     * System-wide sweep across every active queue entry — used by the
     * notification sync and by both Group & Panel Assignment's "Needs
     * Attention" card and Panelist Management's conflict badges.
     */
    public function scanAllConflicts(): Collection
    {
        $entries = QueueEntry::whereNull('removed_at')
            ->whereHas('attemptSchedule.presentationAttempt.attemptPanelAssignments', function ($q) {
                $q->whereHas('assignmentStatus', fn ($sq) => $sq->whereNotIn('code', ['REPLACED', 'WITHDRAWN']));
            })
            ->with('attemptSchedule.presentationAttempt.researchGroup', 'attemptSchedule.presentationAttempt.presentationStatus')
            ->get();

        return $this->scanEntries($entries);
    }

    /**
     * Re-keys a scan (conflictsInRoom()/scanAllConflicts()) by the id of
     * every attempt involved, restricted to one category's own groups.
     *
     * scanEntries() deduplicates a conflicting pair down to a single row so
     * it is only ever reported once, but a conflict is a property of *both*
     * groups — Group & Panel Assignment's roster (2026-09-22) marks every
     * row that is in one, so each side has to be able to find itself. Each
     * entry is described from the point of view of the row it is keyed
     * under: the panelist it shares, and where else that panelist is
     * expected to be at the same time.
     *
     * An attempt with a recorded outcome is deliberately left out — it has
     * already presented, so its panel can no longer be reassigned and
     * flagging it would only be noise. The still-pending side of that same
     * pair is still keyed, which is where the fix actually belongs.
     */
    public function indexByAttempt(Collection $conflicts, int $categoryId): Collection
    {
        $byAttempt = collect();

        foreach ($conflicts as $item) {
            $sides = [
                [$item['attempt'], $item['conflict']->presentationAttempt],
                [$item['conflict']->presentationAttempt, $item['attempt']],
            ];

            foreach ($sides as [$subject, $other]) {
                $subject->loadMissing(['researchGroup', 'presentationStatus']);
                $other->loadMissing(['researchGroup.category', 'attemptSchedule']);

                if ($subject->researchGroup->category_id !== $categoryId) {
                    continue;
                }

                if ($subject->presentationStatus?->is_terminal) {
                    continue;
                }

                $panelistName = trim(($item['conflict']->panelist->profile->first_name ?? '') . ' ' . ($item['conflict']->panelist->profile->last_name ?? ''));
                $otherStart = $other->attemptSchedule?->planned_start_at;

                $rows = $byAttempt->get($subject->id, collect());

                if ($rows->contains(fn (array $row) => $row['panelistId'] === $item['conflict']->panelist_user_id && $row['otherAttemptId'] === $other->id)) {
                    continue;
                }

                $byAttempt->put($subject->id, $rows->push([
                    'panelistId' => $item['conflict']->panelist_user_id,
                    'panelistName' => $panelistName !== '' ? $panelistName : ($item['conflict']->panelist->username ?? 'A panelist'),
                    'otherAttemptId' => $other->id,
                    'otherGroup' => $other->researchGroup->group_reference,
                    'otherCategory' => $other->researchGroup->category->name ?? 'another category',
                    'otherTime' => $otherStart?->format('M j, Y g:i A'),
                    'message' => sprintf(
                        '%s is also assigned to %s (%s) at %s, which overlaps this group\'s own slot.',
                        $panelistName !== '' ? $panelistName : 'A panelist',
                        $other->researchGroup->group_reference,
                        $other->researchGroup->category->name ?? 'another category',
                        $otherStart?->format('M j, Y g:i A') ?? 'the same time'
                    ),
                ]));
            }
        }

        return $byAttempt;
    }

    /**
     * Re-raises (deduplicated) a PANELIST_SCHEDULE_CONFLICT notification for
     * every currently-conflicting attempt, and clears any such notification
     * for an attempt that no longer conflicts — same dedupe-then-clear
     * pattern already used by EventActivationService::flagOverdueDates()/
     * flagUnscheduledGroups(). Called both from the shared 20s admin poll
     * and, synchronously, right after a queue-adjustment move so the bell
     * doesn't wait up to 20s to reflect what an admin just did.
     */
    public function syncNotifications(NotificationService $notifications): void
    {
        $conflicts = $this->scanAllConflicts();
        $activeAttemptIds = [];

        foreach ($conflicts as $item) {
            $attempt = $item['attempt'];
            $activeAttemptIds[] = $attempt->id;

            $alreadyRaised = Notification::where('notification_type', 'PANELIST_SCHEDULE_CONFLICT')
                ->where('related_type', PresentationAttempt::class)
                ->where('related_id', $attempt->id)
                ->whereNull('read_at')
                ->exists();

            if (! $alreadyRaised) {
                $notifications->notifyRole(
                    'ADMIN',
                    'PANELIST_SCHEDULE_CONFLICT',
                    'Panelist scheduling conflict',
                    $item['message'],
                    $attempt
                );
            }
        }

        Notification::where('notification_type', 'PANELIST_SCHEDULE_CONFLICT')
            ->where('related_type', PresentationAttempt::class)
            ->whereNotIn('related_id', array_values(array_unique($activeAttemptIds)) ?: [0])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Shared loop behind conflictsInRoom()/scanAllConflicts(): for each
     * entry's attempt, looks up its own live panelists and finds anything
     * else those panelists are also live-assigned to within the buffer,
     * deduplicating a (attemptA, attemptB, panelist) pair so a conflict
     * found from either side is only reported once.
     */
    private function scanEntries(Collection $entries): Collection
    {
        $results = collect();
        $seenPairs = [];

        foreach ($entries as $entry) {
            $schedule = $entry->attemptSchedule;
            $attempt = $schedule?->presentationAttempt;

            if (! $schedule || ! $attempt) {
                continue;
            }

            // Same rule as conflictingAssignments()' own exclusion, applied
            // to the side being scanned from: a presentation that already
            // happened can no longer clash with anything.
            if ($attempt->presentationStatus?->is_terminal) {
                continue;
            }

            $panelistIds = AttemptPanelAssignment::where('presentation_attempt_id', $attempt->id)
                ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
                ->pluck('panelist_user_id')
                ->all();

            if (empty($panelistIds)) {
                continue;
            }

            foreach ($this->conflictingAssignments($schedule, $panelistIds) as $conflict) {
                $pairKey = implode('-', [
                    min($attempt->id, $conflict->presentation_attempt_id),
                    max($attempt->id, $conflict->presentation_attempt_id),
                    $conflict->panelist_user_id,
                ]);

                if (isset($seenPairs[$pairKey])) {
                    continue;
                }

                $seenPairs[$pairKey] = true;

                $results->push([
                    'attempt' => $attempt,
                    'conflict' => $conflict,
                    'panelistId' => $conflict->panelist_user_id,
                    'message' => $this->messageFor($attempt, $conflict),
                ]);
            }
        }

        return $results;
    }
}
