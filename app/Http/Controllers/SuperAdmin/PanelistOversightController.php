<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttemptPanelAssignment;
use App\Models\AttemptSchedule;
use App\Models\College;
use App\Models\User;
use App\Services\PanelistConflictService;
use Illuminate\Http\Request;

class PanelistOversightController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('q');
        $collegeId = $request->query('college');

        $panelists = User::whereHas('userRoles.role', fn ($q) => $q->where('code', 'PANELIST'))
            ->with(['profile', 'accountStatus', 'panelistProfile.college'])
            ->orderBy('username')
            ->get();

        if ($search) {
            $needle = strtolower($search);
            $panelists = $panelists->filter(function ($panelist) use ($needle) {
                return str_contains(strtolower($panelist->username), $needle)
                    || str_contains(strtolower($panelist->profile->first_name ?? ''), $needle)
                    || str_contains(strtolower($panelist->profile->last_name ?? ''), $needle);
            });
        }

        if ($collegeId) {
            $panelists = $panelists->filter(
                fn ($panelist) => (string) $panelist->panelistProfile?->college_id === (string) $collegeId
            );
        }

        $panelists = $panelists->values();

        return view('super-admin.panelists.index', [
            'panelists' => $panelists,
            'panelistIds' => $panelists->pluck('id')->values()->all(),
            'search' => $search,
            'colleges' => College::where('is_active', true)->orderBy('name')->get(),
            'selectedCollege' => $collegeId,
        ]);
    }

    /**
     * View-only drawer payload (2026-09-20) — replaces the old full-page
     * show view. Mirrors Admin\PanelistController::show()'s JSON shape
     * (profile fields + cross-category assigned-groups history) minus the
     * action-dropdown-only fields, since Super Admin never acts on a
     * panelist here.
     */
    public function show(User $panelist, PanelistConflictService $conflicts)
    {
        abort_unless($panelist->hasRole('PANELIST'), 404);

        $panelist->load(['profile', 'accountStatus', 'panelistProfile.college']);

        $assignmentData = $this->assignedGroupsPayload($panelist);

        $panelistConflicts = $conflicts->scanAllConflicts()->filter(fn (array $item) => $item['panelistId'] === $panelist->id);

        $fullName = trim(implode(' ', array_filter([
            $panelist->profile->first_name ?? null,
            $panelist->profile->middle_name ?? null,
            $panelist->profile->last_name ?? null,
            $panelist->profile->suffix ?? null,
        ])));

        return response()->json([
            'id' => $panelist->id,
            'fullName' => $fullName !== '' ? $fullName : $panelist->username,
            'username' => $panelist->username,
            'contactNumber' => $panelist->profile->contact_number,
            'sex' => $panelist->profile->sex,
            'college' => $panelist->panelistProfile?->college?->name,
            'specialization' => $panelist->panelistProfile?->specialization,
            'accountStatus' => $panelist->accountStatus?->code,
            'accountStatusName' => $panelist->accountStatus?->name,
            'summary' => $assignmentData['summary'],
            'assignedGroups' => $assignmentData['rows'],
            'hasConflict' => $panelistConflicts->isNotEmpty(),
            'conflictMessages' => $panelistConflicts->pluck('message')->unique()->values(),
        ]);
    }

    /**
     * Same cross-category assigned-groups list + summary counts as
     * Admin\PanelistController's drawer, duplicated rather than shared
     * across controllers in different namespaces for one query each.
     */
    private function assignedGroupsPayload(User $panelist): array
    {
        $assignments = AttemptPanelAssignment::where('panelist_user_id', $panelist->id)
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->whereHas('presentationAttempt.researchGroup.category.categoryStatus', fn ($q) => $q->where('is_terminal', false))
            ->with([
                'presentationAttempt.presentationStatus',
                'presentationAttempt.presentationRun',
                'presentationAttempt.researchGroup.category.presentationMode',
                'presentationAttempt.researchGroup.students' => fn ($q) => $q->orderByDesc('is_leader')->orderBy('id'),
                'presentationAttempt.attemptSchedule.queueEntry',
                'presentationAttempt.attemptSchedule.presentationDateRoom.presentationDate.eventDateStatus',
                'presentationAttempt.attemptPanelAssignments' => fn ($q) => $q->whereHas('assignmentStatus', fn ($sq) => $sq->whereNotIn('code', ['REPLACED', 'WITHDRAWN'])),
                'presentationAttempt.attemptPanelAssignments.panelist.profile',
                'presentationAttempt.attemptPanelAssignments.assignmentKind',
            ])
            ->get();

        $assignedCategories = $assignments->pluck('presentationAttempt.researchGroup.category_id')->unique()->count();
        $assignedGroups = $assignments->filter(fn (AttemptPanelAssignment $a) => $a->presentationAttempt->presentationStatus?->code !== 'COMPLETED')->count();
        $evaluatedGroups = $assignments->filter(fn (AttemptPanelAssignment $a) => $a->presentationAttempt->presentationStatus?->code === 'COMPLETED')->count();

        $rows = $assignments->map(function (AttemptPanelAssignment $assignment) use ($panelist) {
            $attempt = $assignment->presentationAttempt;
            $group = $attempt->researchGroup;
            $category = $group->category;
            $schedule = $attempt->attemptSchedule;
            $queueEntry = $schedule?->queueEntry;
            $isCompleted = $attempt->presentationStatus?->code === 'COMPLETED';
            $isDeferred = ! $isCompleted && $queueEntry && $queueEntry->removed_at !== null;
            $status = $isCompleted ? 'completed' : ($isDeferred ? 'deferred' : 'other');

            if ($isCompleted) {
                $realTime = $attempt->presentationRun?->completed_at ?? $attempt->completed_at;
                $dateTimeDisplay = $realTime ? $realTime->format('M j, Y g:i A') : '—';
                $sortAt = $realTime ?? $schedule?->planned_start_at;
            } elseif ($isDeferred) {
                $dateTimeDisplay = 'Deferred';
                $sortAt = $schedule?->planned_start_at;
            } elseif ($schedule?->isAwaitingReschedule($attempt)) {
                $dateTimeDisplay = AttemptSchedule::AWAITING_SHORT;
                $sortAt = null;
            } else {
                $dateTimeDisplay = $schedule?->planned_start_at
                    ? $schedule->planned_start_at->format('M j, Y g:i A') . '–' . $schedule->planned_end_at?->format('g:i A')
                    : '—';
                $sortAt = $schedule?->planned_start_at;
            }

            $isTitleProposal = $category?->presentationMode?->code === 'TITLE_PROPOSAL';
            $titleOrLeader = $isTitleProposal
                ? ($group->leader()?->full_name ?? '—')
                : ($group->current_project_title ?? '—');

            $otherPanels = $attempt->attemptPanelAssignments
                ->where('panelist_user_id', '!=', $panelist->id)
                ->map(function (AttemptPanelAssignment $pa) {
                    $name = trim(($pa->panelist->profile->first_name ?? '') . ' ' . ($pa->panelist->profile->last_name ?? ''));

                    return [
                        'name' => $name !== '' ? $name : $pa->panelist->username,
                        'kind' => $pa->assignmentKind->code === 'BACKUP_PANELIST' ? 'backup' : 'assigned',
                    ];
                })->values();

            return [
                'attemptId' => $attempt->id,
                'status' => $status,
                'dateTimeDisplay' => $dateTimeDisplay,
                'sortAt' => $sortAt?->toIso8601String() ?? '',
                'titleOrLeader' => $titleOrLeader,
                'category' => $category->name ?? '—',
                'otherPanels' => $otherPanels,
            ];
        })->sortBy('sortAt')->values();

        return [
            'summary' => [
                'assignedCategories' => $assignedCategories,
                'assignedGroups' => $assignedGroups,
                'evaluatedGroups' => $evaluatedGroups,
            ],
            'rows' => $rows,
        ];
    }
}
