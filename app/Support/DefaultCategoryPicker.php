<?php

namespace App\Support;

use App\Models\PresentationCategory;
use App\Models\PresentationDate;
use Illuminate\Support\Collection;

/**
 * Which category a category-scoped admin module opens on.
 *
 * Reports, Event Control and Group & Panel Assignment each used to start on a
 * grid of category cards. They open straight on one now (user-directed
 * 2026-09-17) — picking again on every visit was a step with no decision in
 * it — so all three need the same answer to "which one?", and share this one
 * so that where a category is eligible in more than one module they agree,
 * instead of each module landing somewhere else.
 *
 * "Active or ongoing" first: a category with a day underway right now, then
 * one that still has a day it can run, then simply the newest. Each module
 * decides for itself which categories are even eligible (a generated queue, a
 * submitted evaluation, a configured date) and hands that list in; this only
 * chooses between them, so a module whose list excludes the running category
 * still lands on its own best candidate.
 *
 * Requires the candidates' presentationDates (and their eventDateStatus, for
 * isOpenForScheduling) to be loaded — every caller already needs them.
 */
class DefaultCategoryPicker
{
    /**
     * @param  Collection<int, PresentationCategory>  $categories  newest first
     */
    public static function pick(Collection $categories): ?PresentationCategory
    {
        return $categories->first(fn (PresentationCategory $category) => $category->presentationDates
                ->contains(fn (PresentationDate $date) => $date->isRunning()))
            ?? $categories->first(fn (PresentationCategory $category) => $category->presentationDates
                ->contains(fn (PresentationDate $date) => $date->isOpenForScheduling()))
            ?? $categories->first();
    }

    /**
     * The same list, guaranteed to contain the category actually being viewed.
     * A module's own eligibility rule can exclude it — a direct URL to a
     * category with no submitted evaluation yet, say — and a picker that lists
     * every category except the open one reads as a bug.
     *
     * @param  Collection<int, PresentationCategory>  $categories
     * @return Collection<int, PresentationCategory>
     */
    public static function withCurrent(Collection $categories, PresentationCategory $current): Collection
    {
        return $categories
            ->reject(fn (PresentationCategory $other) => $other->id === $current->id)
            ->prepend($current)
            ->sortByDesc('created_at')
            ->values();
    }
}
