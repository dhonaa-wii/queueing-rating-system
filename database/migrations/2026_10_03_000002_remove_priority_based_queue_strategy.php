<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User-directed 2026-10-03: the Priority Based queue strategy is removed.
     * Queue Generation never supported it (nothing captures a group's
     * priority), and no category_queue_settings row referenced it, so
     * deleting the row strands nothing.
     */
    public function up(): void
    {
        DB::table('queue_strategies')->where('code', 'PRIORITY_BASED')->delete();
    }

    public function down(): void
    {
        if (DB::table('queue_strategies')->where('code', 'PRIORITY_BASED')->exists()) {
            return;
        }

        DB::table('queue_strategies')->insert([
            'code' => 'PRIORITY_BASED',
            'name' => 'Priority Based',
            'is_active' => true,
        ]);
    }
};
