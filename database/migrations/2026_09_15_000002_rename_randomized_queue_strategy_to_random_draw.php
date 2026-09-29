<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User-directed 2026-09-15: RANDOMIZED becomes RANDOM_DRAW ("Random
     * Draw"), backed by a stored seed instead of a fresh shuffle on every
     * queue regeneration — see QueueGenerationService::orderByRandomDraw().
     * Renamed in place so category_queue_settings FKs stay intact, and any
     * setting already on this strategy gets its seed drawn here so it never
     * reaches plan() without one.
     */
    public function up(): void
    {
        DB::table('queue_strategies')
            ->where('code', 'RANDOMIZED')
            ->update(['code' => 'RANDOM_DRAW', 'name' => 'Random Draw']);

        $strategyId = DB::table('queue_strategies')->where('code', 'RANDOM_DRAW')->value('id');

        if ($strategyId === null) {
            return;
        }

        DB::table('category_queue_settings')
            ->where('queue_strategy_id', $strategyId)
            ->get()
            ->each(function ($setting) {
                $settings = json_decode($setting->settings_json ?? '[]', true) ?: [];

                if (empty($settings['random_draw_seed'])) {
                    $settings['random_draw_seed'] = bin2hex(random_bytes(32));

                    DB::table('category_queue_settings')
                        ->where('id', $setting->id)
                        ->update(['settings_json' => json_encode($settings)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('queue_strategies')
            ->where('code', 'RANDOM_DRAW')
            ->update(['code' => 'RANDOMIZED', 'name' => 'Randomized']);
    }
};
