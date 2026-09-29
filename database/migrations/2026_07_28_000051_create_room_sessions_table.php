<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_event_id')->constrained('presentation_events');
            $table->foreignId('presentation_date_room_id')->constrained('presentation_date_rooms');
            $table->foreignId('room_session_status_id')->constrained('room_session_statuses');
            $table->foreignId('current_attempt_id')->nullable()->constrained('presentation_attempts');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('ended_by_user_id')->nullable()->constrained('users');

            $table->unique(['presentation_event_id', 'presentation_date_room_id'], 'room_sessions_event_date_room_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_sessions');
    }
};
