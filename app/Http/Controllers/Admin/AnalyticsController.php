<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PresentationCategory;
use App\Models\User;
use App\Services\ScheduleAnalyticsService;
use App\Support\AdminCollege;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Schedule Analytics — the "Analytics" half of Reports & Analytics.
 *
 * Deliberately cross-category by default (user-directed 2026-09-17), which is
 * what makes it a sidebar sibling of Reports rather than a tab inside one
 * category: totals across every defense that has actually run are the point,
 * and no category picker could show them. `?category=` narrows it.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, ScheduleAnalyticsService $analytics)
    {
        // Only categories that have actually run a room session can appear in
        // the filter — anything else would select an empty page.
        $categories = PresentationCategory::forAdminCollege()->whereHas('presentationDates.presentationDateRooms.roomSessions')
            ->orderBy('name')
            ->get(['id', 'name']);

        $selected = $categories->firstWhere('id', (int) $request->query('category'));

        return view('admin.analytics.index', [
            'categories' => $categories,
            'selected' => $selected,
            'data' => $analytics->analyze($selected),
            'panelists' => $this->panelistWorkload($selected),
        ]);
    }

    /**
     * Every ACTIVE panelist of the Admin's college with their seat counts
     * (user-directed 2026-10-14), same split as the panelist drawer (§2.68):
     * Assigned = every live seat, Upcoming = attempts still to present
     * (deferred included), Completed = COMPLETED. Narrowed to the selected
     * category when there is one; a panelist with no seats still lists, at 0.
     */
    private function panelistWorkload(?PresentationCategory $selected): Collection
    {
        $seats = function ($q, ?\Closure $status = null) use ($selected) {
            $q->whereHas('assignmentStatus', fn ($s) => $s->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
                ->whereHas('presentationAttempt', function ($a) use ($selected, $status) {
                    $a->whereHas('researchGroup', fn ($g) => $selected
                        ? $g->where('category_id', $selected->id)
                        : $g->whereHas('category', fn ($c) => $c->forAdminCollege()));
                    if ($status) {
                        $a->whereHas('presentationStatus', $status);
                    }
                });
        };

        return User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'PANELIST'))
            ->whereHas('panelistProfile', fn ($q) => $q->where('college_id', AdminCollege::id() ?? 0))
            ->whereHas('accountStatus', fn ($q) => $q->where('code', 'ACTIVE'))
            ->with(['profile', 'panelistProfile.college'])
            ->withCount([
                'attemptPanelAssignments as assigned_count' => fn ($q) => $seats($q),
                'attemptPanelAssignments as upcoming_count' => fn ($q) => $seats($q, fn ($s) => $s->where('is_terminal', false)),
                'attemptPanelAssignments as completed_count' => fn ($q) => $seats($q, fn ($s) => $s->where('code', 'COMPLETED')),
            ])
            ->get()
            ->map(function (User $user) {
                $p = $user->profile;
                $name = trim(collect([
                    $p?->first_name,
                    $p?->middle_name ? mb_substr($p->middle_name, 0, 1) . '.' : null,
                    $p?->last_name,
                    $p?->suffix,
                ])->filter()->implode(' '));

                return [
                    'name' => $name !== '' ? $name : $user->username,
                    'username' => $user->username,
                    'department' => $user->panelistProfile?->departmentName(),
                    'assigned' => (int) $user->assigned_count,
                    'upcoming' => (int) $user->upcoming_count,
                    'completed' => (int) $user->completed_count,
                ];
            })
            ->sortBy([['assigned', 'desc'], ['name', 'asc']])
            ->values();
    }
}
