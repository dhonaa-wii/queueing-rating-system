<?php

namespace App\Services;

use App\Models\EvaluationSubmission;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use App\Models\ResearchGroup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Panel Participation Summary (user-directed 2026-10-23, replacing the
 * Panelist Sign-off Sheet): one running sheet for the whole category listing,
 * per panelist, how many groups they served as Technical Adviser, as Chair and
 * as Member. It keeps growing until the category ends and can be viewed,
 * printed or exported at any time.
 *
 * Only groups that were actually evaluated count — a COMPLETED attempt with at
 * least one SUBMITTED/FINALIZED sheet:
 *  - Chair / Member: the panelist submitted a sheet for that attempt and held a
 *    non-Alternate seat on it (is_lead → Chair). An Alternate seat never counts;
 *    an Alternate who replaced a member was re-seated as an assigned panelist
 *    and counts as that.
 *  - Adviser: the evaluated group is one of the panelist's advisees, by the
 *    same rule as My Advisees (AdviseeService): technical_adviser_user_id, else
 *    a name match only when it uniquely identifies a category-college panelist.
 * Each column counts distinct groups, so a re-defense doesn't count a group
 * twice in the same role.
 */
class PanelParticipationService
{
    private const COUNTED_STATUSES = ['SUBMITTED', 'FINALIZED'];

    public function __construct(private AdviseeService $advisees)
    {
    }

    /**
     * @return object{
     *     rows: Collection<int, object{name: string, adviser_count: int, chair_count: int, member_count: int}>,
     *     start: ?Carbon, end: ?Carbon, day_count: int,
     *     rooms: Collection<int, string>, panelist_count: int, group_count: int
     * }
     */
    public function sheet(PresentationCategory $category): object
    {
        $submissions = $this->submissions($category);
        $days = $this->runDays($category);
        $rows = $this->rows($category, $submissions);

        return (object) [
            'rows' => $rows,
            'start' => $days->isEmpty() ? null : $days->min('start'),
            'end' => $days->isEmpty() ? null : $days->max('end'),
            'day_count' => $days->pluck('start')->map->toDateString()->unique()->count(),
            'rooms' => $this->roomsUsed($category),
            'panelist_count' => $rows->count(),
            'group_count' => $submissions
                ->map(fn (EvaluationSubmission $s) => $s->presentationAttempt?->research_group_id)
                ->filter()->unique()->count(),
        ];
    }

    /**
     * One row per panelist with at least one count, alphabetical by name.
     */
    private function rows(PresentationCategory $category, Collection $submissions): Collection
    {
        /** @var array<int, array{user: ?User, adviser: array, chair: array, member: array}> $tally */
        $tally = [];

        $touch = function (int $userId, ?User $user) use (&$tally): void {
            $tally[$userId] ??= ['user' => $user, 'adviser' => [], 'chair' => [], 'member' => []];
            $tally[$userId]['user'] ??= $user;
        };

        foreach ($submissions as $submission) {
            $attempt = $submission->presentationAttempt;
            $groupId = $attempt?->research_group_id;

            if (! $groupId) {
                continue;
            }

            $seats = $attempt->attemptPanelAssignments
                ->where('panelist_user_id', $submission->panelist_user_id)
                ->reject->isAlternate();

            // A seat that was later replaced still describes the role the
            // panelist sat in when they filed; prefer a live one if both exist.
            $seat = $seats->first(fn ($a) => ! in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true))
                ?? $seats->first();

            if (! $seat) {
                continue;
            }

            $touch($submission->panelist_user_id, $submission->panelist);
            $tally[$submission->panelist_user_id][$seat->is_lead ? 'chair' : 'member'][$groupId] = true;
        }

        $evaluatedGroupIds = $submissions
            ->map(fn (EvaluationSubmission $s) => $s->presentationAttempt?->research_group_id)
            ->filter()->unique()->values();

        // Use the same unique-legacy-name resolver as My Advisees, so an
        // ambiguous legacy name is not credited to multiple panelists.
        $collegePanelists = $this->advisees->panelistsInCollege((int) ($category->college_id ?? 0));

        ResearchGroup::whereIn('id', $evaluatedGroupIds)
            ->with('technicalAdviser.profile')
            ->get()
            ->each(function (ResearchGroup $group) use ($collegePanelists, $touch, &$tally) {
                $adviserId = $this->advisees->resolvedAdviserUserId($group, $collegePanelists);
                if ($adviserId === null) {
                    return;
                }

                $adviser = $group->technical_adviser_user_id
                    ? $group->technicalAdviser
                    : $collegePanelists->firstWhere('id', $adviserId);

                if ($adviser) {
                    $touch($adviserId, $adviser);
                    $tally[$adviserId]['adviser'][$group->id] = true;
                }
            });

        return collect($tally)
            ->map(fn (array $t, int $userId) => (object) [
                'user_id' => $userId,
                'name' => $this->displayName($t['user']),
                'adviser_count' => count($t['adviser']),
                'chair_count' => count($t['chair']),
                'member_count' => count($t['member']),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function displayName(?User $user): string
    {
        $profile = $user?->profile;

        return trim(collect([
            $profile?->first_name,
            $profile?->middle_name ? mb_substr($profile->middle_name, 0, 1) . '.' : null,
            $profile?->last_name,
            $profile?->suffix,
        ])->filter()->implode(' ')) ?: ($user?->username ?? '—');
    }

    /**
     * Each day the event has actually run, from its real start to its real
     * end — or to today while it is still running. A planned day nobody
     * started has no activated_at and is not a day the event ran.
     *
     * @return Collection<int, object{start: Carbon, end: Carbon}>
     */
    private function runDays(PresentationCategory $category): Collection
    {
        return PresentationDate::where('category_id', $category->id)
            ->whereNotNull('activated_at')
            ->get()
            ->map(fn (PresentationDate $date) => (object) [
                'start' => $date->activated_at->copy()->startOfDay(),
                'end' => ($date->completed_at ?? now())->copy()->startOfDay(),
            ]);
    }

    /**
     * Every room that opened for this category (has a room session), by name.
     *
     * @return Collection<int, string>
     */
    private function roomsUsed(PresentationCategory $category): Collection
    {
        return PresentationDateRoom::whereHas('presentationDate', fn ($q) => $q->where('category_id', $category->id))
            ->whereHas('roomSessions')
            ->pluck('room_name')
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function submissions(PresentationCategory $category): Collection
    {
        return EvaluationSubmission::whereHas('submissionStatus', fn ($q) => $q->whereIn('code', self::COUNTED_STATUSES))
            ->whereHas('presentationAttempt.presentationStatus', fn ($q) => $q->where('code', 'COMPLETED'))
            ->whereHas('presentationAttempt.researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->with([
                'panelist.profile',
                'presentationAttempt.attemptPanelAssignments.assignmentKind',
                'presentationAttempt.attemptPanelAssignments.assignmentStatus',
            ])
            ->get();
    }
}
