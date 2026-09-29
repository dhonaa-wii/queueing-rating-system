<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryResearchTrack;
use App\Models\PresentationCategory;
use App\Models\Student;
use App\Services\QueueGenerationService;
use App\Support\CategorySetupLock;
use Illuminate\Http\Request;

/**
 * A category's research track list (user-directed 2026-09-29). Students pick
 * their track from it at registration, and registered rooms can be limited
 * to some of it (TrackRouting). Every change re-lays the queue, since it can
 * change which room a group belongs in.
 */
class CategoryTrackController extends Controller
{
    public function store(Request $request, PresentationCategory $category, QueueGenerationService $queue)
    {
        CategorySetupLock::guard($category, 'add a track');

        $name = $this->validatedName($request);

        $existing = $category->researchTracks()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        if ($existing?->is_active) {
            return $this->back($category)->with('error', "\"{$name}\" is already on the list.");
        }

        $existing
            ? $existing->update(['is_active' => true, 'name' => $name])
            : CategoryResearchTrack::create(['category_id' => $category->id, 'name' => $name, 'is_active' => true]);

        $queue->relayoutAfterTrackChange($category, $request->user()->id);

        return $this->back($category)->with('status', "Track \"{$name}\" added.");
    }

    /** A rename carries over to every student in the category already on the track. */
    public function update(Request $request, PresentationCategory $category, CategoryResearchTrack $track, QueueGenerationService $queue)
    {
        abort_unless($track->category_id === $category->id, 404);
        CategorySetupLock::guard($category, 'rename a track');

        $name = $this->validatedName($request);

        $duplicate = $category->researchTracks()
            ->where('id', '!=', $track->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($duplicate) {
            return $this->back($category)->with('error', "\"{$name}\" is already on the list.");
        }

        Student::whereHas('researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->whereRaw('LOWER(TRIM(research_track_name)) = ?', [mb_strtolower($track->name)])
            ->update(['research_track_name' => $name]);

        $track->update(['name' => $name]);

        $queue->relayoutAfterTrackChange($category, $request->user()->id);

        return $this->back($category)->with('status', "Track renamed to \"{$name}\".");
    }

    /**
     * Removes the track from the list and from every room. Students keep the
     * track they registered with; their groups now go to the rooms with no
     * tracks, like any track no room takes.
     */
    public function destroy(Request $request, PresentationCategory $category, CategoryResearchTrack $track, QueueGenerationService $queue)
    {
        abort_unless($track->category_id === $category->id, 404);
        CategorySetupLock::guard($category, 'remove a track');

        $name = $track->name;
        $track->delete();

        $queue->relayoutAfterTrackChange($category, $request->user()->id);

        return $this->back($category)->with('status', "Track \"{$name}\" removed.");
    }

    private function validatedName(Request $request): string
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:150']]);

        return trim(preg_replace('/\s+/', ' ', $validated['name']));
    }

    private function back(PresentationCategory $category)
    {
        return redirect()->to(route('admin.categories.show', $category) . '#tab-overview');
    }
}
