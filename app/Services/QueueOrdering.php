<?php

namespace App\Services;

use App\Models\AdjustmentReason;
use App\Models\PresentationCategory;
use App\Models\QueueAdjustment;
use App\Models\QueueEntry;
use App\Models\ResearchGroup;

/**
 * Where a group belongs in its category's queue, as a comparable rank
 * (user-directed 2026-10-11). Lower presents first:
 *
 *  - Section Based: each section in the configured Section Order, then
 *    unlisted sections A–Z, then groups with no section — the same order
 *    QueueGenerationService::orderBySection() lays a queue out in.
 *  - FIFO and Random Draw: every group shares one rank; their order comes
 *    from registration time / the draw, not from a rank.
 *  - A group deferred and left unresolved at the end of its day (requeued
 *    with UNRESOLVED_DEFERRED_END_OF_DAY) presents after every section — after
 *    the last one configured or registered, whatever its own section is.
 *
 * Nothing here knows any particular section: the order is built from the
 * category's own Section Order and the sections its groups registered with,
 * at any year level (1A–9Z), and any number of them.
 *  - A pending re-defense presents after everyone (ReDefenseService), unless
 *    an Administrator moved or transferred it (QueueEntry::adminPlacedIds()).
 *
 * Used for a late registration's place (QueueGenerationService::
 * appendUnqueuedGroups()), for keeping order when groups spill or carry over
 * to another day, and for the positions a Move or Transfer may use.
 */
class QueueOrdering
{
    public const NO_SECTION = 900_000;

    public const TAIL = 1_000_000;

    public const RE_DEFENSE = 2_000_000;

    /** @var array<int, array<string, int>> */
    private static array $ranks = [];

    public static function forget(?int $categoryId = null): void
    {
        if ($categoryId === null) {
            self::$ranks = [];
        } else {
            unset(self::$ranks[$categoryId]);
        }
    }

    public function isSectionBased(PresentationCategory $category): bool
    {
        $category->loadMissing('categoryQueueSetting.queueStrategy');

        return $category->categoryQueueSetting?->queueStrategy?->code === 'SECTION_BASED';
    }

    public function sectionOf(?ResearchGroup $group): string
    {
        if (! $group) {
            return '';
        }

        $group->loadMissing('students');

        return ResearchGroupRegistrationService::normalizeSection($group->leader()?->section_name);
    }

    /** The rank of a group presenting normally (not deferred-tail, not re-defense). */
    public function groupRank(PresentationCategory $category, ?ResearchGroup $group): int
    {
        if (! $this->isSectionBased($category)) {
            return 0;
        }

        $section = $this->sectionOf($group);

        if ($section === '') {
            return self::NO_SECTION;
        }

        $ranks = $this->sectionRanks($category);

        if (! isset($ranks[$section])) {
            self::forget($category->id);
            $ranks = $this->sectionRanks($category, [$section]);
        }

        return $ranks[$section];
    }

    /**
     * Ranks for queue entries, keyed by entry id. One query for the entries
     * and one for the tail marker, so a whole room is cheap.
     *
     * @return array<int, int>
     */
    public function entryRanks(PresentationCategory $category, iterable $entryIds): array
    {
        $ids = collect($entryIds)->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $entries = QueueEntry::whereIn('id', $ids)
            ->with('attemptSchedule.presentationAttempt.attemptType', 'attemptSchedule.presentationAttempt.presentationStatus', 'attemptSchedule.presentationAttempt.researchGroup.students')
            ->get();

        $tailIds = $this->tailEntryIds($ids);
        $adminPlaced = QueueEntry::adminPlacedIds($ids);
        $ranks = [];

        foreach ($entries as $entry) {
            $attempt = $entry->attemptSchedule?->presentationAttempt;

            $ranks[$entry->id] = match (true) {
                // A re-defense an Administrator moved or transferred ranks like
                // the group it is, so later insertions don't jump ahead of it.
                $attempt?->attemptType?->code === 'RE_DEFENSE' && ! $attempt->presentationStatus?->is_terminal && ! isset($adminPlaced[$entry->id]) => self::RE_DEFENSE,
                isset($tailIds[$entry->id]) => self::TAIL,
                default => $this->groupRank($category, $attempt?->researchGroup),
            };
        }

        return $ranks;
    }

    /** A short name for a rank, for messages and the Move/Transfer guides. */
    public function label(PresentationCategory $category, int $rank): string
    {
        return match (true) {
            $rank >= self::RE_DEFENSE => 'Re-Defense',
            $rank >= self::TAIL => 'Deferred',
            $rank >= self::NO_SECTION => 'No section',
            default => (string) (array_search($rank, $this->sectionRanks($category), true) ?: '—'),
        };
    }

    /**
     * Entries that were requeued after an unresolved defer. Once requeued
     * that way a group stays behind every section for that attempt.
     *
     * @return array<int, true>
     */
    private function tailEntryIds($ids): array
    {
        $reasonId = AdjustmentReason::where('code', 'UNRESOLVED_DEFERRED_END_OF_DAY')->value('id');

        if (! $reasonId) {
            return [];
        }

        return QueueAdjustment::whereIn('queue_entry_id', $ids)
            ->where('reason_id', $reasonId)
            ->pluck('queue_entry_id')
            ->unique()
            ->mapWithKeys(fn ($id) => [$id => true])
            ->all();
    }

    /** @return array<string, int> section => rank, in presenting order. */
    private function sectionRanks(PresentationCategory $category, array $extra = []): array
    {
        if (isset(self::$ranks[$category->id])) {
            return self::$ranks[$category->id];
        }

        $order = array_values($category->categoryQueueSetting?->sectionOrder() ?? []);
        $position = array_flip($order);

        $sections = ResearchGroup::where('category_id', $category->id)
            ->with('students')
            ->get()
            ->map(fn (ResearchGroup $group) => $this->sectionOf($group))
            ->merge($order)
            ->merge($extra)
            ->filter(fn ($section) => $section !== '')
            ->unique()
            ->sort(function (string $a, string $b) use ($position) {
                if (isset($position[$a]) || isset($position[$b])) {
                    return ($position[$a] ?? PHP_INT_MAX) <=> ($position[$b] ?? PHP_INT_MAX);
                }

                return strnatcasecmp($a, $b);
            })
            ->values();

        return self::$ranks[$category->id] = $sections->flip()->all();
    }
}
