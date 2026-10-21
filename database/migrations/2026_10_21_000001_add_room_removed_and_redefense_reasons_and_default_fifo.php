<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 2026-10-21, user-directed:
     *  - ROOM_REMOVED: groups queued in a room taken off a day move to the
     *    rooms the category still uses (QueueAdjustmentService::
     *    evacuateRemovedRoom()).
     *  - RE_DEFENSE_LAST: a re-defense moved so it still presents after every
     *    first attempt (QueueAdjustmentService::keepReDefenseLast()).
     *    Both are written by the system only, so they are inactive: the
     *    Move/Transfer reason dropdowns list active rows, the code looks these
     *    up by code.
     *  - Every category without a saved queue strategy gets FIFO, the default
     *    the queue already used for an unset strategy.
     */
    public function up(): void
    {
        DB::table('adjustment_reasons')->updateOrInsert(
            ['code' => 'ROOM_REMOVED'],
            ['name' => 'Room Removed From Day', 'is_active' => false]
        );

        DB::table('adjustment_reasons')->updateOrInsert(
            ['code' => 'RE_DEFENSE_LAST'],
            ['name' => 'Re-Defense Kept Last', 'is_active' => false]
        );

        $fifoId = DB::table('queue_strategies')->where('code', 'FIFO')->value('id');

        if ($fifoId) {
            $now = now();

            DB::table('presentation_categories')
                ->whereNotIn('id', DB::table('category_queue_settings')->select('category_id'))
                ->pluck('id')
                ->each(fn ($categoryId) => DB::table('category_queue_settings')->insert([
                    'category_id' => $categoryId,
                    'queue_strategy_id' => $fifoId,
                    'called_waiting_minutes' => 5,
                    'allow_same_day_reinsertion' => true,
                    'late_defer_enabled' => true,
                    'unresolved_absent_end_of_day' => true,
                    'settings_json' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
        }
    }

    public function down(): void
    {
        DB::table('adjustment_reasons')->whereIn('code', ['ROOM_REMOVED', 'RE_DEFENSE_LAST'])->delete();
    }
};
