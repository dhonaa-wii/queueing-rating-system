<?php

namespace App\Console\Commands;

use App\Models\PresentationCategory;
use Illuminate\Console\Command;

class RefreshCategoryStatuses extends Command
{
    protected $signature = 'categories:refresh-statuses';

    protected $description = 'Re-derives each non-terminal presentation category\'s status from setup completeness and the registration window, so pure time passing (e.g. a registration window opening or closing) keeps category_status_id accurate without requiring an admin action.';

    public function handle(): int
    {
        $categories = PresentationCategory::with('categoryStatus')
            ->whereHas('categoryStatus', fn ($query) => $query->where('is_terminal', false))
            ->get();

        $updated = 0;

        foreach ($categories as $category) {
            $before = $category->category_status_id;
            $category->refreshStatus();

            if ($category->category_status_id !== $before) {
                $updated++;
            }
        }

        $this->info("Checked {$categories->count()} categories, updated {$updated}.");

        return self::SUCCESS;
    }
}
