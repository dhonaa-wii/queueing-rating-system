<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PresentationCategory;
use App\Services\ScheduleAnalyticsService;
use Illuminate\Http\Request;

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
        ]);
    }
}
