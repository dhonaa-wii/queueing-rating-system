<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_panel_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->constrained('presentation_attempts');
            $table->foreignId('panelist_user_id')->constrained('users');
            $table->foreignId('assignment_kind_id')->constrained('panel_assignment_kinds');
            $table->foreignId('assignment_status_id')->constrained('panel_assignment_statuses');
            $table->foreignId('assigned_by')->constrained('users');
            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();
            $table->text('remarks')->nullable();

            $table->unique(['presentation_attempt_id', 'panelist_user_id'], 'attempt_panel_assignments_attempt_panelist_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_panel_assignments');
    }
};
