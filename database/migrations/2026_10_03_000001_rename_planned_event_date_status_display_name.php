<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Planned" is an internal scheduling idea, not something the UI shows
 * (user-directed 2026-10-03). Only the display name changes; the code
 * PLANNED that every status check reads is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_date_statuses')->where('code', 'PLANNED')->update(['name' => 'Upcoming']);
    }

    public function down(): void
    {
        DB::table('event_date_statuses')->where('code', 'PLANNED')->update(['name' => 'Planned']);
    }
};
