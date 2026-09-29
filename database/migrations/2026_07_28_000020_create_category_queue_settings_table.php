<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_queue_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->unique()->constrained('presentation_categories');
            $table->foreignId('queue_strategy_id')->constrained('queue_strategies');
            $table->smallInteger('called_waiting_minutes')->unsigned()->default(5);
            $table->boolean('allow_same_day_reinsertion')->default(true);
            $table->boolean('late_defer_enabled')->default(true);
            $table->boolean('unresolved_absent_end_of_day')->default(true);
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_queue_settings');
    }
};
