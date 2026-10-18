<?php

namespace App\Services;

use App\Models\ResearchGroup;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which groups a panelist advises — shared by My Advisees and the Panelist
 * Dashboard's advisee summary.
 *
 * A group belongs to a panelist when research_groups.technical_adviser_user_id
 * is theirs (set since 2026-10-19 from the adviser dropdown, backfilled for
 * older groups). A group with no linked account falls back to the old name
 * match (PanelAssignmentService::nameMatchesPanelist(), either name order)
 * within the panelist's own college — name only, because a group linked to
 * another account belongs to that account even if the names are the same.
 */
class AdviseeService
{
    public function groupsFor(User $user): Collection
    {
        $user->loadMissing(['profile', 'panelistProfile']);

        $variants = PanelAssignmentService::panelistNameVariants($user);
        $collegeId = $user->panelistProfile?->college_id ?? 0;

        return ResearchGroup::query()
            ->where(fn ($q) => $q->where('technical_adviser_user_id', $user->id)
                ->orWhere(fn ($legacy) => $legacy->whereNull('technical_adviser_user_id')
                    ->whereNotNull('technical_adviser_name')
                    ->where('technical_adviser_name', '!=', '')
                    ->whereHas('category', fn ($c) => $c->where('college_id', $collegeId))))
            ->with([
                'category.academicYear',
                'category.semester',
                'category.college',
                'students',
                'proposedTitles' => fn ($q) => $q->orderBy('sort_order'),
                'presentationAttempts' => fn ($q) => $q->orderBy('attempt_number'),
                'presentationAttempts.presentationStatus',
                'presentationAttempts.finalOutcome',
                'presentationAttempts.attemptSchedule.presentationDateRoom.presentationDate.eventDateStatus',
                'presentationAttempts.attemptSchedule.queueEntry',
                'presentationAttempts.attemptPanelAssignments.assignmentStatus',
                'presentationAttempts.attemptPanelAssignments.assignmentKind',
                'presentationAttempts.attemptPanelAssignments.panelist.profile',
                // Only submitted/finalized sheets — a draft is not something
                // View Evaluation can render.
                'presentationAttempts.evaluationSubmissions' => fn ($q) => $q->whereHas(
                    'submissionStatus',
                    fn ($sq) => $sq->whereIn('code', ['SUBMITTED', 'FINALIZED'])
                ),
            ])
            ->get()
            ->filter(fn (ResearchGroup $group) => $group->technical_adviser_user_id === $user->id
                || in_array(PanelAssignmentService::normalizeName((string) $group->technical_adviser_name), $variants, true))
            ->values();
    }

    public function advises(User $user, ResearchGroup $group): bool
    {
        $user->loadMissing(['profile', 'panelistProfile']);

        if ($group->technical_adviser_user_id) {
            return $group->technical_adviser_user_id === $user->id;
        }

        return $group->category
            && $group->category->college_id === $user->panelistProfile?->college_id
            && PanelAssignmentService::nameMatchesPanelist((string) $group->technical_adviser_name, $user);
    }
}
