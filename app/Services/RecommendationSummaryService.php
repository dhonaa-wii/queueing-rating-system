<?php

namespace App\Services;

use App\Models\EvaluationSubmission;
use App\Models\PresentationAttempt;
use App\Models\PresentationCategory;
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
 */
class RecommendationSummaryService
{
    private const COUNTED_STATUSES = ['SUBMITTED', 'FINALIZED'];

    /**
     * @return Collection<int, object{
     *     attempt_id: int,
     *     sequence: int,
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
        return PresentationAttempt::whereHas('researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->whereHas('presentationStatus', fn ($q) => $q->where('code', 'COMPLETED'))
            ->with([
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
                        'panelist' => $this->panelistName($submission),
                        'comment' => trim($submission->remarks),
                    ])
                    ->values(),
            ]);
    }

    private function panelistName(EvaluationSubmission $submission): string
    {
        $panelist = $submission->panelist;
        $profile = $panelist?->profile;

        return trim(collect([
            $profile?->first_name,
            $profile?->middle_name ? mb_substr($profile->middle_name, 0, 1) . '.' : null,
            $profile?->last_name,
            $profile?->suffix,
        ])->filter()->implode(' ')) ?: ($panelist?->username ?? '—');
    }
}
