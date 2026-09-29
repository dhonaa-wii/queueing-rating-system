<?php

namespace App\Http\Controllers\Panelist;

use App\Http\Controllers\Controller;
use App\Models\PresentationCategory;
use App\Services\CategoryScheduleViewService;
use App\Support\DefaultCategoryPicker;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * View Schedule opens straight on a category rather than on a grid of
     * cards (user-directed 2026-09-20, same reasoning as the three admin
     * modules in DefaultCategoryPicker's own doc comment) — picking again on
     * every visit was a step with no decision in it. DefaultCategoryPicker
     * picks the one with a day underway, else the nearest one still able to
     * run, else simply the newest; the picker itself moved onto the
     * workspace as a dropdown. This route only renders something of its own
     * when no category has a schedule at all.
     */
    public function index()
    {
        $categories = $this->scheduledCategories();
        $category = DefaultCategoryPicker::pick($categories);

        if (! $category) {
            return view('panelist.schedule.index');
        }

        return redirect()->route('panelist.schedule.show', $category);
    }

    /**
     * Every category with a configured schedule, not just the ones this
     * panelist is personally assigned to — user-directed: view schedules
     * across all categories, rooms, and room statuses, same flow as the
     * Student schedule page. Unlike the public Student index, this isn't
     * filtered to "publicly visible" statuses (DRAFT/ARCHIVED etc. stay
     * listed) since panelists are internal staff, not the public.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PresentationCategory>
     */
    private function scheduledCategories()
    {
        $categories = PresentationCategory::whereHas('presentationDates')
            ->with(['academicYear', 'semester', 'presentationDates'])
            ->orderByDesc('created_at')
            ->get();

        $categories->each(function (PresentationCategory $category) {
            $category->presentationDates->each->refreshStatus();
        });
        $categories->load('presentationDates.eventDateStatus');

        return $categories;
    }

    public function show(Request $request, PresentationCategory $category, CategoryScheduleViewService $scheduleViewService)
    {
        $category->refreshStatus();

        $viewModel = $scheduleViewService->build(
            $category,
            trim((string) $request->query('q', '')),
            $request->query('group')
        );

        // The header's category picker, which replaced the back link — View
        // Schedule opens straight on a category, so there is no picker page
        // to go back to.
        $viewModel['pickerCategories'] = DefaultCategoryPicker::withCurrent($this->scheduledCategories(), $category);

        return view('panelist.schedule.show', $viewModel);
    }
}
