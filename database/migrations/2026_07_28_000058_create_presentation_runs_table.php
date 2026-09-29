<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->unique()->constrained('presentation_attempts');
            $table->foreignId('room_session_id')->constrained('room_sessions');
            $table->dateTime('called_at')->nullable();
            $table->dateTime('waiting_deadline_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->integer('configured_duration_seconds')->unsigned();
            $table->integer('actual_duration_seconds')->unsigned()->nullable();
            $table->integer('total_paused_seconds')->unsigned()->default(0);
            $table->integer('extended_seconds')->unsigned()->default(0);
            $table->foreignId('timer_status_id')->constrained('timer_statuses');
            $table->dateTime('last_action_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_runs');
    }
};
