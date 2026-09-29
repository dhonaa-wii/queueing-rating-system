<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User-directed 2026-08-05: the "time has arrived, waiting for the
     * admin's Start action" event_date_statuses code is renamed from READY
     * to STANDBY — READY is already overloaded elsewhere in this schema
     * (category_statuses.READY_FOR_QUEUE, event_statuses.READY,
     * queue_entries' derived READY_NEXT), and STANDBY reads correctly as
     * "holding, nothing has started yet" rather than implying anything is
     * already moving. Updates the existing row in place so
     * presentation_dates.event_date_status_id FKs stay intact.
     */
    public function up(): void
    {
        DB::table('event_date_statuses')
            ->where('code', 'READY')
            ->update(['code' => 'STANDBY', 'name' => 'Standby']);
    }

    public function down(): void
    {
        DB::table('event_date_statuses')
            ->where('code', 'STANDBY')
            ->update(['code' => 'READY', 'name' => 'Ready']);
    }
};
