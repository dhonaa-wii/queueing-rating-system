<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryAnnouncement;
use App\Models\PresentationCategory;
use App\Support\CategorySetupLock;
use Illuminate\Http\Request;

class CategoryAnnouncementController extends Controller
{
    public function store(Request $request, PresentationCategory $category)
    {
        CategorySetupLock::guard($category, 'post an announcement');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $category->categoryAnnouncements()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Announcement posted.');
    }

    public function update(Request $request, PresentationCategory $category, CategoryAnnouncement $announcement)
    {
        abort_unless($announcement->category_id === $category->id, 404);

        CategorySetupLock::guard($category, 'update this announcement');

        // The payment announcement's text belongs to the Payment tab; only
        // its visibility can be changed here.
        if ($announcement->isPaymentInstructions()) {
            $announcement->update(['is_active' => $request->boolean('is_active')]);

            return back()->with('status', 'Announcement updated.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $announcement->update($validated);

        return back()->with('status', 'Announcement updated.');
    }

    public function destroy(PresentationCategory $category, CategoryAnnouncement $announcement)
    {
        abort_unless($announcement->category_id === $category->id, 404);

        CategorySetupLock::guard($category, 'delete this announcement');

        if ($announcement->isPaymentInstructions()) {
            return back()->with('error', 'The payment instructions announcement is managed from the Payment tab. Clear the instructions there to remove it.');
        }

        $announcement->delete();

        return back()->with('status', 'Announcement deleted.');
    }
}
