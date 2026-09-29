<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PresentationCategory;
use App\Services\CategoryScheduleViewService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = PresentationCategory::with([
            'academicYear',
            'semester',
            'college',
            'categoryStatus',
            'presentationMode',
            'presentationDates' => fn ($query) => $query->chronological(),
            'categoryPaymentSetting',
            'categoryPaymentTypes',
            'categoryAnnouncements' => fn ($query) => $query->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->latest('starts_at'),
        ])
            ->whereHas('categoryStatus', fn ($query) => $query->whereNotIn('code', ['DRAFT', 'ARCHIVED']))
            ->orderByDesc('created_at')
            ->get();

        // Real-time-derived registration window — recompute on every view so the
        // Open/Closed badge reflects the clock as it ticks, same as the Admin index.
        $categories->each->refreshStatus();
        $categories->load('categoryStatus');

        // A category can flip to a hidden status (e.g. gets archived) between the
        // query and the refresh above — drop it rather than showing a stale card.
        $categories = $categories->reject(fn (PresentationCategory $category) => ! $category->isPubliclyVisible());

        return view('student.categories.index', ['categories' => $categories]);
    }

    public function schedule(Request $request, PresentationCategory $category, CategoryScheduleViewService $scheduleViewService)
    {
        $category->refreshStatus();
        abort_unless($category->isPubliclyVisible(), 404);

        $viewModel = $scheduleViewService->build(
            $category,
            trim((string) $request->query('q', '')),
            $request->query('group')
        );

        return view('student.categories.schedule', $viewModel);
    }
}
