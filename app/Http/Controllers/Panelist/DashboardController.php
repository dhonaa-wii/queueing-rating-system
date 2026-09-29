<?php

namespace App\Http\Controllers\Panelist;

use App\Http\Controllers\Controller;
use App\Models\AttemptPanelAssignment;
use App\Models\EvaluationSubmission;
use App\Models\PresentationDateRoom;
use App\Services\RoomQueuePreviewService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, RoomQueuePreviewService $roomQueuePreviewService)
    {
        $user = $request->user();

        $assignments = AttemptPanelAssignment::where('panelist_user_id', $user->id)
            ->whereHas('assignmentStatus', fn ($q) => $q->whereNotIn('code', ['REPLACED', 'WITHDRAWN']))
            ->with([
                'assignmentKind',
                'assignmentStatus',
                'presentationAttempt.presentationStatus',
                'presentationAttempt.researchGroup.category.academicYear',
                'presentationAttempt.researchGroup.category.semester',
                'presentationAttempt.researchGroup.category.college',
                'presentationAttempt.researchGroup.students',
                'presentationAttempt.attemptSchedule.presentationDateRoom.presentationDate.eventDateStatus',
                'presentationAttempt.attemptSchedule.presentationDateRoom.roomSessions.roomSessionStatus',
                // Read by AttemptSchedule::isAwaitingReschedule() for every
                // row in the assignments table — loaded here so it stays free.
                'presentationAttempt.attemptSchedule.queueEntry',
            ])
            ->get()
            ->filter(fn (AttemptPanelAssignment $assignment) => $assignment->presentationAttempt?->researchGroup?->category !== null)
            ->values();

        // A panelist can hold assignments across more than one category
        // (functional-spec §9.7) — the dashboard is scoped to one at a time,
        // switcher only rendered when there's actually more than one.
        $categories = $assignments
            ->pluck('presentationAttempt.researchGroup.category')
            ->unique('id')
            ->sortBy('name')
            ->values();

        $selectedCategoryId = (int) $request->query('category', $categories->first()?->id);
        $selectedCategory = $categories->firstWhere('id', $selectedCategoryId) ?? $categories->first();

        $categoryAssignments = $selectedCategory
            ? $assignments->filter(
                fn (AttemptPanelAssignment $assignment) => (int) $assignment->presentationAttempt->researchGroup->category_id === (int) $selectedCategory->id
            )->values()
            : collect();

        $assignedRooms = $categoryAssignments
            ->map(fn (AttemptPanelAssignment $assignment) => $assignment->presentationAttempt->attemptSchedule?->presentationDateRoom)
            ->filter()
            ->unique('id')
            ->sortBy(fn (PresentationDateRoom $room) => $room->presentationDate->presentation_date->format('Y-m-d').' '.($room->room_start_time ?? '').' '.$room->room_name)
            ->values();

        // Same "recompute derived status on every relevant page load" convention
        // used by Admin's category/live-monitoring controllers — reload the
        // relation afterward since refreshStatus() only updates the FK column,
        // not the already-loaded belongsTo relation object.
        $presentationDates = $assignedRooms->pluck('presentationDate')->filter()->unique('id');
        $presentationDates->each->refreshStatus();
        $presentationDates->each(fn ($date) => $date->load('eventDateStatus'));

        $roomPreviews = $assignedRooms->mapWithKeys(fn (PresentationDateRoom $room) => [
            $room->id => $roomQueuePreviewService->forRoom($room),
        ]);

        $assignedPresentationsCount = $categoryAssignments->count();

        // Nothing writes EvaluationSubmission rows yet — this will genuinely
        // be 0 completed / all-pending today, which is the honest state
        // (assignments exist, none have been evaluated yet), not a fake number.
        $completedEvaluationsCount = $selectedCategory
            ? EvaluationSubmission::where('panelist_user_id', $user->id)
                ->whereHas('presentationAttempt.researchGroup', fn ($q) => $q->where('category_id', $selectedCategory->id))
                ->whereHas('submissionStatus', fn ($q) => $q->where('code', 'FINALIZED'))
                ->count()
            : 0;
        $pendingEvaluationsCount = max($assignedPresentationsCount - $completedEvaluationsCount, 0);
        $evaluationCompletionPercent = $assignedPresentationsCount > 0
            ? (int) round($completedEvaluationsCount / $assignedPresentationsCount * 100)
            : 0;

        // Per-category assignment counts, shown next to each option in the
        // category switcher — only meaningful once a panelist has more than
        // one category.
        $categoryCounts = $categories->count() > 1
            ? $categories->mapWithKeys(fn ($category) => [
                $category->id => $assignments->filter(
                    fn (AttemptPanelAssignment $assignment) => (int) $assignment->presentationAttempt->researchGroup->category_id === (int) $category->id
                )->count(),
            ])
            : collect();

        // Compact "what's next" list for the dashboard overview — non-terminal
        // assignments only (completed/awaiting-reschedule rows belong on the
        // full My Assignments page, not this glance view), nearest first. Sort
        // key is a formatted string rather than a raw Carbon instance, since a
        // null (unscheduled) planned time sorts last instead of throwing.
        $upcomingAssignments = $categoryAssignments
            ->filter(fn (AttemptPanelAssignment $assignment) => ! $assignment->presentationAttempt->presentationStatus->is_terminal)
            ->sortBy(fn (AttemptPanelAssignment $assignment) => $assignment->presentationAttempt->attemptSchedule?->planned_start_at?->format('Y-m-d H:i:s') ?? '9999-12-31 23:59:59')
            ->take(6)
            ->values();

        $announcements = $selectedCategory
            ? $selectedCategory->categoryAnnouncements()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->latest('starts_at')
                ->get()
            : collect();

        return view('panelist.dashboard', [
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'categoryAssignments' => $categoryAssignments,
            'assignedRooms' => $assignedRooms,
            'roomPreviews' => $roomPreviews,
            'assignedPresentationsCount' => $assignedPresentationsCount,
            'completedEvaluationsCount' => $completedEvaluationsCount,
            'pendingEvaluationsCount' => $pendingEvaluationsCount,
            'evaluationCompletionPercent' => $evaluationCompletionPercent,
            'categoryCounts' => $categoryCounts,
            'upcomingAssignments' => $upcomingAssignments,
            'announcements' => $announcements,
        ]);
    }
}
