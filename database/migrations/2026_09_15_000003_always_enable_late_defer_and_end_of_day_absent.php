<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User-directed 2026-09-15: "Allow deferring late groups" and "Mark
     * unresolved groups absent at end of day" are no longer per-category
     * checkboxes — both are always on. The columns stay (same treatment as
     * allow_same_day_reinsertion); existing rows are brought in line.
     */
    public function up(): void
    {
        DB::table('category_queue_settings')->update([
            'late_defer_enabled' => true,
            'unresolved_absent_end_of_day' => true,
        ]);
    }

    public function down(): void
    {
        // Previous per-category choices are not recoverable; nothing to undo.
    }
};
