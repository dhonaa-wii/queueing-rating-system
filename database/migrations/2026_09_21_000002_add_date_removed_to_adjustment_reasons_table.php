<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backs QueueAdjustmentService::prepareDateForRemoval() (Admin\
     * PresentationDateController::destroy(), 2026-09-21): a group relocated
     * off a date that's being deleted needs its own adjustment_reasons row,
     * distinct from the existing end-of-day codes (this fires from an
     * explicit admin delete, not end-of-day processing).
     */
    public function up(): void
    {
        DB::table('adjustment_reasons')->updateOrInsert(
            ['code' => 'DATE_REMOVED'],
            ['name' => 'Presentation Date Removed', 'is_active' => true]
        );
    }

    public function down(): void
    {
        DB::table('adjustment_reasons')->where('code', 'DATE_REMOVED')->delete();
    }
};
