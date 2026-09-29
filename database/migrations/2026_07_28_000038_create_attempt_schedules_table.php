<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->unique()->constrained('presentation_attempts');
            $table->foreignId('presentation_date_room_id')->constrained('presentation_date_rooms');
            $table->dateTime('planned_call_at')->nullable();
            $table->dateTime('planned_start_at')->nullable();
            $table->dateTime('planned_end_at')->nullable();
            $table->dateTime('adjusted_expected_at')->nullable();
            $table->foreignId('scheduled_by')->constrained('users');
            $table->dateTime('scheduled_at');
            $table->foreignId('change_reason_id')->nullable()->constrained('adjustment_reasons');
            $table->text('remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_schedules');
    }
};
