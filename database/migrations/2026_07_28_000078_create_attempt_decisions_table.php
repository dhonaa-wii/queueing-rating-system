<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->constrained('presentation_attempts');
            $table->foreignId('presentation_outcome_id')->constrained('presentation_outcomes');
            $table->foreignId('decision_status_id')->constrained('decision_statuses');
            $table->foreignId('approved_proposed_title_id')->nullable()->constrained('proposed_titles');
            $table->text('recommendation')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->dateTime('recorded_at');
            $table->foreignId('finalized_by')->nullable()->constrained('users');
            $table->dateTime('finalized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_decisions');
    }
};
