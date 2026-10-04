<?php

namespace App\Http\Controllers;

use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use Illuminate\Support\Collection;

/**
 * Public landing page. Lists the presentations a student can still act on —
 * one presenting right now, one taking registrations (or about to), or one
 * with a presentation day still ahead — so the page opens on what is
 * happening instead of a role picker. The full list, closed ones included,
 * stays at student.categories.index.
 */
class LandingController extends Controller
{
    private const LIMIT = 6;

    public function __invoke()
    {
        $categories = PresentationCategory::with([
            'academicYear',
            'semester',
            'college',
            'categoryStatus',
            'presentationMode',
            'presentationDates' => fn ($query) => $query->chronological()->with('eventDateStatus'),
        ])
            ->whereHas('categoryStatus', fn ($query) => $query->whereNotIn('code', ['DRAFT', 'ARCHIVED', 'COMPLETED']))
            ->orderByDesc('created_at')
            ->get();

        // Same real-time refresh the public category list does, so the
        // registration badge follows the clock.
        $categories->each->refreshStatus();
        $categories->load('categoryStatus');

        $presentations = $categories
            ->filter(fn (PresentationCategory $category) => $category->isPubliclyVisible() && ! $category->isCompleted())
            ->map(fn (PresentationCategory $category) => $this->describe($category))
            ->filter()
            ->sortBy([['rank', 'asc'], ['sort_at', 'asc']])
            ->values();

        return view('landing', [
            'presentations' => $presentations->take(self::LIMIT),
            'presentationTotal' => $presentations->count(),
        ]);
    }

    /**
     * Null when the presentation has nothing left to act on (registration
     * closed and no day still ahead) — it stays on the full list only.
     */
    private function describe(PresentationCategory $category): ?array
    {
        $dates = $category->presentationDates;
        $running = $dates->first(fn (PresentationDate $date) => $date->isRunning());
        $nextDate = $dates->first(fn (PresentationDate $date) => $date->isOpenForScheduling());
        $registration = $category->registrationWindowStatus();

        if ($running) {
            $state = ['rank' => 0, 'label' => 'Presenting Now', 'tone' => 'success', 'live' => true,
                'detail' => 'Today · ' . $running->presentation_date->format('M j, Y'),
                'sort_at' => $running->presentation_date->timestamp];
        } elseif ($registration === 'Open') {
            $state = ['rank' => 1, 'label' => 'Registration Open', 'tone' => 'accent', 'live' => false,
                'detail' => 'Closes ' . $category->registration_closes_at->format('M j, Y · g:i A'),
                'sort_at' => $category->registration_closes_at->timestamp];
        } elseif ($registration === 'Scheduled') {
            $state = ['rank' => 2, 'label' => 'Opening Soon', 'tone' => 'info', 'live' => false,
                'detail' => 'Registration opens ' . $category->registration_opens_at->format('M j, Y · g:i A'),
                'sort_at' => $category->registration_opens_at->timestamp];
        } elseif ($nextDate) {
            $state = ['rank' => 3, 'label' => 'Upcoming', 'tone' => 'muted', 'live' => false,
                'detail' => 'Presents ' . $nextDate->presentation_date->format('M j, Y'),
                'sort_at' => $nextDate->presentation_date->timestamp];
        } else {
            return null;
        }

        return $state + [
            'category' => $category,
            'canRegister' => $registration === 'Open',
            'nextDate' => $running ?? $nextDate,
            'dayCount' => $dates->filter(fn (PresentationDate $date) => $date->isOpenForScheduling())->count(),
        ];
    }
}
