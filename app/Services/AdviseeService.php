<?php

namespace App\Services;

use App\Models\ResearchGroup;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which groups a panelist advises — shared by My Advisees and the Panelist
 * Dashboard's advisee summary.
 *
 * research_groups.technical_adviser_name is stored by name, not by user id
 * (the registration dropdown lists the college's panelists by name), so a
 * group belongs to a panelist when that name matches them — the same test
 * the adviser-vs-panel conflict check uses
 * (PanelAssignmentService::nameMatchesPanelist(), either name order) —
 * within the panelist's own college, since the adviser list is
 * college-scoped and a namesake in another college is someone else.
 */
class AdviseeService
{
    public function groupsFor(User $user): Collection
    {
        $user->loadMissing(['profile', 'panelistProfile']);

        $variants = PanelAssignmentService::panelistNameVariants($user);
        if ($variants === []) {
            return collect();
        }

        return ResearchGroup::query()
            ->whereNotNull('technical_adviser_name')
            ->where('technical_adviser_name', '!=', '')
            ->whereHas('category', fn ($q) => $q->where('college_id', $user->panelistProfile?->college_id ?? 0))
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
            ->filter(fn (ResearchGroup $group) => in_array(
                PanelAssignmentService::normalizeName($group->technical_adviser_name),
                $variants,
                true
            ))
            ->values();
    }

    public function advises(User $user, ResearchGroup $group): bool
    {
        $user->loadMissing(['profile', 'panelistProfile']);

        return $group->category
            && $group->category->college_id === $user->panelistProfile?->college_id
            && PanelAssignmentService::nameMatchesPanelist((string) $group->technical_adviser_name, $user);
    }
}
