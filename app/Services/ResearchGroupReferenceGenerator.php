<?php

namespace App\Services;

use App\Models\PresentationCategory;
use App\Models\ResearchGroup;

class ResearchGroupReferenceGenerator
{
    public function generate(PresentationCategory $category): string
    {
        $prefix = $this->prefixFor($category);
        $year = $category->academicYear->start_year ?? now()->year;

        $sequence = ResearchGroup::where('category_id', $category->id)->count() + 1;
        $reference = sprintf('%s-%d-%04d', $prefix, $year, $sequence);

        while (ResearchGroup::where('group_reference', $reference)->exists()) {
            $sequence++;
            $reference = sprintf('%s-%d-%04d', $prefix, $year, $sequence);
        }

        return $reference;
    }

    private function prefixFor(PresentationCategory $category): string
    {
        preg_match_all('/[A-Za-z0-9]+/', $category->name, $words);
        $initials = collect($words[0])->map(fn ($word) => strtoupper($word[0]))->implode('');

        return substr($initials, 0, 4) ?: 'CAT';
    }
}
