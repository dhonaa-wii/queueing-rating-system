<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User-directed 2026-09-15: the Alternating Sections queue strategy is
     * removed entirely, to be replaced by a better strategy. No
     * category_queue_settings row referenced it (every category was FIFO),
     * so deleting the row strands nothing.
     */
    public function up(): void
    {
        DB::table('queue_strategies')->where('code', 'ALTERNATING_SECTIONS')->delete();
    }

    public function down(): void
    {
        if (DB::table('queue_strategies')->where('code', 'ALTERNATING_SECTIONS')->exists()) {
            return;
        }

        DB::table('queue_strategies')->insert([
            'code' => 'ALTERNATING_SECTIONS',
            'name' => 'Alternating Sections',
            'is_active' => true,
        ]);
    }
};
