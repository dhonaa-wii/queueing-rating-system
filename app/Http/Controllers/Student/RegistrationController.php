<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PresentationCategory;
use App\Models\ResearchGroup;
use App\Services\QueueGenerationService;
use App\Services\ResearchGroupRegistrationService;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function create(PresentationCategory $category)
    {
        $category->refreshStatus();
        abort_unless($category->isPubliclyVisible(), 404);

        $category->load('presentationMode', 'academicYear', 'semester', 'college');

        return view('student.registrations.create', [
            'category' => $category,
            'memberSlots' => max(0, $category->maximum_members - 1),
            'isTitleProposal' => $category->presentationMode->code === 'TITLE_PROPOSAL',
        ]);
    }

    public function store(Request $request, PresentationCategory $category, ResearchGroupRegistrationService $registrationService, QueueGenerationService $queueService)
    {
        $category->refreshStatus();
        abort_unless($category->isPubliclyVisible(), 404);

        if ($category->registrationWindowStatus() !== 'Open') {
            return back()->withErrors([
                'registration' => 'Registration for this category is not currently open.',
            ])->withInput();
        }

        $isTitleProposal = $category->presentationMode->code === 'TITLE_PROPOSAL';
        $data = $registrationService->validate($request, $category, $isTitleProposal);

        $registrationService->guardAgainstDuplicate($category, $data['leader'], $data['project_title'], $isTitleProposal);

        $researchGroup = $registrationService->create($category, $data, $isTitleProposal);

        // Registration can now stay open while a queue already exists/the
        // event is ongoing (user-directed 2026-09-02), so a freshly
        // self-registered group needs the same round-robin placement an
        // Admin-added group already gets (Admin\PanelAssignmentController::
        // storeGroup()) — just fire-and-forget from the student's side.
        // There's no authenticated admin here to attribute this to, so it
        // uses the category's own creating admin, same fallback
        // EventActivationService already uses for other category-level
        // automatic actions. A capacity shortfall or similar just leaves
        // the group unqueued for now — it registered successfully either
        // way, and an admin can resolve it later.
        $queueService->syncAfterRegistrationChange($category->fresh(), $category->created_by);

        return redirect()->route('student.categories.registration.confirmation', [$category, $researchGroup->group_reference]);
    }

    public function confirmation(PresentationCategory $category, string $groupReference)
    {
        $researchGroup = ResearchGroup::where('category_id', $category->id)
            ->where('group_reference', $groupReference)
            ->with(['students' => fn ($query) => $query->orderByDesc('is_leader'), 'proposedTitles' => fn ($query) => $query->orderBy('sort_order')])
            ->firstOrFail();

        $category->load('presentationMode', 'academicYear', 'semester');

        return view('student.registrations.confirmation', [
            'category' => $category,
            'researchGroup' => $researchGroup,
        ]);
    }
}
