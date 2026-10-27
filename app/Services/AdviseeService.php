<?php

namespace App\Services;

use App\Models\ResearchGroup;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which groups a panelist advises — shared by My Advisees and the Panelist
 * Dashboard's advisee summary.
 *
 * A group belongs to its linked adviser account. Unlinked legacy groups are
 * matched only when the stored name identifies exactly one panelist in that
 * category's college.
 */
class AdviseeService
{
    public function groupsFor(User $user): Collection
    {
        $user->loadMissing(['profile', 'panelistProfile']);

        $collegeId = $user->panelistProfile?->college_id ?? 0;
        $collegePanelists = $this->panelistsInCollege((int) $collegeId);

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
            ->filter(fn (ResearchGroup $group) => $this->resolvedAdviserUserId($group, $collegePanelists) === (int) $user->id)
            ->values();
    }

    public function advises(User $user, ResearchGroup $group): bool
    {
        $user->loadMissing(['profile', 'panelistProfile']);
        $group->loadMissing('category');

        if (! $group->category || ! $user->panelistProfile) {
            return false;
        }

        if ($group->technical_adviser_user_id) {
            return (int) $group->technical_adviser_user_id === (int) $user->id
                && (int) $group->category->college_id === (int) $user->panelistProfile->college_id;
        }

        if ((int) $group->category->college_id !== (int) $user->panelistProfile->college_id) {
            return false;
        }

        $collegePanelists = $this->panelistsInCollege((int) $group->category->college_id);

        return $this->resolvedAdviserUserId($group, $collegePanelists) === (int) $user->id;
    }

    public function resolvedAdviserUserIdForGroup(ResearchGroup $group): ?int
    {
        $group->loadMissing('category');

        if (! $group->category || ! $group->category->college_id) {
            return $group->technical_adviser_user_id === null
                ? null
                : (int) $group->technical_adviser_user_id;
        }

        return $this->resolvedAdviserUserId(
            $group,
            $this->panelistsInCollege((int) $group->category->college_id),
        );
    }

    public function panelistsInCollege(int $collegeId): Collection
    {
        return User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'PANELIST'))
            ->whereHas('panelistProfile', fn ($q) => $q->where('college_id', $collegeId))
            ->with('profile')
            ->get();
    }

    /**
     * Resolve a legacy name only when exactly one account in the category's
     * college matches. Linked groups never fall back to names.
     */
    public function resolvedAdviserUserId(ResearchGroup $group, Collection $collegePanelists): ?int
    {
        if ($group->technical_adviser_user_id !== null) {
            $adviserId = (int) $group->technical_adviser_user_id;

            return $collegePanelists->contains(fn (User $panelist) => (int) $panelist->id === $adviserId)
                ? $adviserId
                : null;
        }

        $name = trim((string) $group->technical_adviser_name);
        if ($name === '') {
            return null;
        }

        $matches = $collegePanelists->filter(
            fn (User $panelist) => PanelAssignmentService::nameMatchesPanelist($name, $panelist)
        );

        return $matches->count() === 1 ? (int) $matches->first()->id : null;
    }
}
