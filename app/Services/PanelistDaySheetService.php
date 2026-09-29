<?php

namespace App\Services;

use App\Models\EvaluationSubmission;
use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use App\Models\PresentationDateRoom;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Panelist sign-off sheet (user-directed 2026-09-25, replacing the per-day/
 * per-room papers of 2026-09-13/17): one running sheet for the whole category,
 * built the same way as the recommendations summary. It lists every panelist
 * who has submitted an evaluation for a completed presentation, with the
 * number of distinct groups they evaluated and a signature line, and it keeps
 * growing until the category ends — nothing is gated on a day having finished,
 * so it can be viewed, printed or exported at any time.
 *
 * The headline strip describes the run so far: the actual span of days the
 * event has been running (first real start -> the latest day run, which moves
 * forward each day until the category finishes), how many days that was, every
 * room that actually opened, and the totals of panelists and groups.
 *
 * Only SUBMITTED/FINALIZED sheets for COMPLETED attempts count — a sign-off has
 * to describe presentations that actually happened.
 */
class PanelistDaySheetService
{
    private const COUNTED_STATUSES = ['SUBMITTED', 'FINALIZED'];

    /**
     * @return object{
     *     rows: Collection<int, object{name: string, group_count: int}>,
     *     start: ?Carbon, end: ?Carbon, day_count: int,
     *     rooms: Collection<int, string>, panelist_count: int, group_count: int
     * }
     */
    public function sheet(PresentationCategory $category): object
    {
        $submissions = $this->submissions($category);
        $days = $this->runDays($category);

        return (object) [
            'rows' => $this->rows($submissions),
            'start' => $days->isEmpty() ? null : $days->min('start'),
            'end' => $days->isEmpty() ? null : $days->max('end'),
            'day_count' => $days->pluck('start')->map->toDateString()->unique()->count(),
            'rooms' => $this->roomsUsed($category),
            'panelist_count' => $submissions->pluck('panelist_user_id')->unique()->count(),
            'group_count' => $submissions
                ->map(fn (EvaluationSubmission $s) => $s->presentationAttempt?->research_group_id)
                ->filter()->unique()->count(),
        ];
    }

    /**
     * One row per panelist, alphabetical by name.
     */
    private function rows(Collection $submissions): Collection
    {
        return $submissions
            ->groupBy('panelist_user_id')
            ->map(function (Collection $forPanelist) {
                $panelist = $forPanelist->first()->panelist;
                $profile = $panelist?->profile;

                return (object) [
                    'name' => trim(collect([
                        $profile?->first_name,
                        $profile?->middle_name ? mb_substr($profile->middle_name, 0, 1) . '.' : null,
                        $profile?->last_name,
                        $profile?->suffix,
                    ])->filter()->implode(' ')) ?: ($panelist?->username ?? '—'),
                    'group_count' => $forPanelist
                        ->map(fn (EvaluationSubmission $s) => $s->presentationAttempt?->research_group_id)
                        ->filter()->unique()->count(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Each day the event has actually run, from its real start to its real
     * end — or to today while it is still running, which is what makes the
     * span's end date move on each day. A planned day nobody started has no
     * activated_at and is not a day the event ran.
     *
     * @return Collection<int, object{start: Carbon, end: Carbon}>
     */
    private function runDays(PresentationCategory $category): Collection
    {
        return PresentationDate::where('category_id', $category->id)
            ->whereNotNull('activated_at')
            ->get()
            ->map(fn (PresentationDate $date) => (object) [
                'start' => $date->activated_at->copy()->startOfDay(),
                'end' => ($date->completed_at ?? now())->copy()->startOfDay(),
            ]);
    }

    /**
     * Every room that opened for this category — one with a room session,
     * which only exists once Start Room was pressed — by name, since the same
     * room is reused across days.
     *
     * @return Collection<int, string>
     */
    private function roomsUsed(PresentationCategory $category): Collection
    {
        return PresentationDateRoom::whereHas('presentationDate', fn ($q) => $q->where('category_id', $category->id))
            ->whereHas('roomSessions')
            ->pluck('room_name')
            ->filter()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function submissions(PresentationCategory $category): Collection
    {
        return EvaluationSubmission::whereHas('submissionStatus', fn ($q) => $q->whereIn('code', self::COUNTED_STATUSES))
            ->whereHas('presentationAttempt.presentationStatus', fn ($q) => $q->where('code', 'COMPLETED'))
            ->whereHas('presentationAttempt.researchGroup', fn ($q) => $q->where('category_id', $category->id))
            ->with(['panelist.profile', 'presentationAttempt'])
            ->get();
    }
}
