<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_schedule_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->unique()->constrained('presentation_categories');
            $table->smallInteger('duration_minutes')->unsigned();
            $table->smallInteger('warning_minutes')->unsigned()->nullable();
            $table->smallInteger('transition_minutes')->unsigned()->default(0);
            $table->boolean('allow_extended_time')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_schedule_settings');
    }
};
