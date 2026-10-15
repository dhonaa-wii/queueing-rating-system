<?php

namespace App\Services;

use App\Models\AttemptPanelAssignment;
use App\Models\EvaluationSubmission;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Recommendations & comments summary (user-directed 2026-09-25): one row per
 * completed presentation, in the order they actually finished (first
 * completed first), carrying the group's proponents and every comment the
 * panelists left on their evaluation sheets.
 *
 * It is a running list across every day of the category — a group appears the
 * moment its presentation completes, so it can be viewed or exported at any
 * point until the last group is done. Nothing is gated on a day having ended,
 * unlike the sign-off sheets.
 *
 * The comment is evaluation_submissions.remarks (the sheet's "Comments" box).
 * Only SUBMITTED/FINALIZED sheets count, and a panelist who filed no comment
 * is left off the row rather than listed with a blank.
 *
 * Since 2026-10-15 the same list is also built for a panelist's advisees
 * (rowsForAdviser()), and form() turns one row into the printable
 * Recommendation Form.
 */
class RecommendationSummaryService
{
    private const COUNTED_STATUSES = ['SUBMITTED', 'FINALIZED'];

    /** Blank numbered lines the form keeps, like the printed paper. */
    public const FORM_MIN_LINES = 24;

    public function __construct(private AdviseeService $advisees)
    {
    }

    /**
     * @return Collection<int, object{
     *     attempt_id: int,
     *     sequence: int,
     *     category_id: int,
     *     category_name: string,
     *     group_reference: ?string,
     *     project_title: ?string,
     *     attempt_number: int,
     *     completed_at: \Illuminate\Support\Carbon|null,
     *     proponents: Collection<int, object{name: string, section: ?string, is_leader: bool}>,
     *     comments: Collection<int, object{panelist: string, comment: string}>
     * }>
     */
    public function rows(PresentationCategory $category): Collection
    {
        return $this->build(
            PresentationAttempt::whereHas('researchGroup', fn ($q) => $q->where('category_id', $category->id))
        );
    }

    /** The same list, limited to the groups this panelist advises (any category). */
    public function rowsForAdviser(User $panelist): Collection
    {
        $groupIds = $this->advisees->groupsFor($panelist)->pluck('id');

        return $this->build(PresentationAttempt::whereIn('research_group_id', $groupIds));
    }

    /**
     * One completed attempt as the Recommendation Form: research title,
     * researchers, actual defense date, the panel's comments as numbered
     * recommendation lines, and the panel that assessed it (Chair in the
     * middle of the sign-off row, like the paper).
     */
    public function form(PresentationAttempt $attempt): object
    {
        $attempt->load([
            'researchGroup.category.academicYear',
            'researchGroup.category.semester',
            'researchGroup.category.college',
            'researchGroup.students',
            'researchGroup.proposedTitles',
            'evaluationSubmissions' => fn ($q) => $q->whereHas(
                'submissionStatus',
                fn ($sq) => $sq->whereIn('code', self::COUNTED_STATUSES)
            ),
            'evaluationSubmissions.panelist.profile',
            'attemptPanelAssignments.assignmentStatus',
            'attemptPanelAssignments.assignmentKind',
            'attemptPanelAssignments.panelist.profile',
        ]);

        $group = $attempt->researchGroup;

        $title = $group->current_project_title;
        if (! $title) {
            $approved = $group->proposedTitles->firstWhere('is_approved', true);
            $title = $approved?->title_text
                ?? $group->proposedTitles->sortBy('sort_order')->pluck('title_text')->filter()->implode('; ');
        }

        $submissions = $attempt->evaluationSubmissions->sortBy('submitted_at')->values();

        $recommendations = $submissions
            ->flatMap(fn (EvaluationSubmission $submission) => preg_split('/\R+/', (string) $submission->remarks))
            // A panelist who numbered or bulleted their own lines would
            // otherwise print "1. 1. …" in the No. column.
            ->map(fn ($line) => trim(preg_replace('/^\s*(?:\d+\s*[.)]|[-•*])\s*/u', '', (string) $line)))
            ->filter(fn ($line) => $line !== '')
            ->values();

        $live = $attempt->attemptPanelAssignments
            ->reject(fn (AttemptPanelAssignment $a) => in_array($a->assignmentStatus?->code, ['REPLACED', 'WITHDRAWN'], true));
        $leadIds = $live->where('is_lead', true)->pluck('panelist_user_id');

        // "Assessed/Recommended by" is whoever actually filed a sheet; before
        // any sheet exists it falls back to the seated (non-alternate) panel.
        $signers = $submissions->isNotEmpty()
            ? $submissions->unique('panelist_user_id')->map(fn ($s) => (object) [
                'name' => $this->personName($s->panelist),
                'is_chair' => $leadIds->contains($s->panelist_user_id),
            ])
            : $live->reject(fn ($a) => $a->assignmentKind?->code === 'BACKUP_PANELIST')->map(fn ($a) => (object) [
                'name' => $this->personName($a->panelist),
                'is_chair' => (bool) $a->is_lead,
            ]);

        $chair = $signers->firstWhere('is_chair', true);
        $members = $signers->reject(fn ($s) => $s === $chair)->values();
        if ($chair) {
            $members->splice(intdiv($members->count() + 1, 2), 0, [$chair]);
        }

        return (object) [
            'attempt' => $attempt,
            'category' => $group->category,
            'group_reference' => $group->group_reference,
            'title' => $title,
            'researchers' => $group->students
                ->sortBy([['is_leader', 'desc'], ['id', 'asc']])
                ->pluck('full_name')
                ->values(),
            'defended_at' => $attempt->completed_at,
            'recommendations' => $recommendations,
            'lines' => max(self::FORM_MIN_LINES, $recommendations->count()),
            'signers' => $members,
        ];
    }

    private function build(Builder $query): Collection
    {
        return $query
            ->whereHas('presentationStatus', fn ($q) => $q->where('code', 'COMPLETED'))
            ->with([
                'researchGroup.category',
                'researchGroup.students',
                'evaluationSubmissions' => fn ($q) => $q->whereHas(
                    'submissionStatus',
                    fn ($sq) => $sq->whereIn('code', self::COUNTED_STATUSES)
                ),
                'evaluationSubmissions.panelist.profile',
            ])
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(fn (PresentationAttempt $attempt, int $index) => (object) [
                'attempt_id' => $attempt->id,
                'sequence' => $index + 1,
                'category_id' => $attempt->researchGroup->category_id,
                'category_name' => $attempt->researchGroup->category?->name ?? '—',
                'group_reference' => $attempt->researchGroup->group_reference,
                'project_title' => $attempt->researchGroup->current_project_title,
                'attempt_number' => (int) $attempt->attempt_number,
                'completed_at' => $attempt->completed_at,
                'proponents' => $attempt->researchGroup->students
                    ->sortBy([['is_leader', 'desc'], ['id', 'asc']])
                    ->map(fn ($student) => (object) [
                        'name' => $student->full_name,
                        'section' => $student->section_name,
                        'is_leader' => (bool) $student->is_leader,
                    ])
                    ->values(),
                'comments' => $attempt->evaluationSubmissions
                    ->filter(fn (EvaluationSubmission $submission) => trim((string) $submission->remarks) !== '')
                    ->sortBy('submitted_at')
                    ->map(fn (EvaluationSubmission $submission) => (object) [
                        'panelist' => $this->personName($submission->panelist),
                        'comment' => trim($submission->remarks),
                    ])
                    ->values(),
            ]);
    }

    private function personName(?User $panelist): string
    {
        $profile = $panelist?->profile;

        return trim(collect([
            $profile?->first_name,
            $profile?->middle_name ? mb_substr($profile->middle_name, 0, 1) . '.' : null,
            $profile?->last_name,
            $profile?->suffix,
        ])->filter()->implode(' ')) ?: ($panelist?->username ?? '—');
    }
}
