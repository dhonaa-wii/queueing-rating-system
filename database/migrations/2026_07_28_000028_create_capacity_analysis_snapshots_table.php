<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacity_analysis_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_date_room_id')->constrained('presentation_date_rooms');
            $table->string('analysis_type', 20);
            $table->dateTime('calculated_at');
            $table->integer('available_seconds')->unsigned();
            $table->integer('configured_duration_seconds')->unsigned();
            $table->integer('groups_considered')->unsigned();
            $table->integer('projected_capacity')->unsigned();
            $table->integer('projected_overflow_count')->unsigned()->default(0);
            $table->dateTime('estimated_completion_at')->nullable();
            $table->smallInteger('recommended_additional_days')->unsigned()->default(0);
            $table->json('calculation_data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacity_analysis_snapshots');
    }
};
