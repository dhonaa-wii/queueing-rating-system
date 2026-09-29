<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_panel_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->constrained('presentation_attempts');
            $table->foreignId('panelist_user_id')->constrained('users');
            $table->tinyInteger('terminal_number')->unsigned();
            $table->foreignId('terminal_type_id')->constrained('terminal_types');
            $table->foreignId('terminal_connection_id')->constrained('terminal_connections');
            $table->dateTime('participation_started_at');
            $table->dateTime('participation_ended_at')->nullable();
            $table->boolean('is_approved_substitute')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_panel_participations');
    }
};
