<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->constrained('presentation_attempts');
            $table->foreignId('panelist_user_id')->constrained('users');
            $table->foreignId('evaluation_form_version_id')->constrained('evaluation_form_versions');
            $table->foreignId('attempt_panel_participation_id')->constrained('attempt_panel_participations');
            $table->foreignId('submission_status_id')->constrained('submission_statuses');
            $table->text('remarks')->nullable();
            $table->decimal('raw_total_score', 12, 4)->nullable();
            $table->decimal('weighted_total_score', 12, 4)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users');
            $table->dateTime('reopened_at')->nullable();
            $table->dateTime('finalized_at')->nullable();

            $table->unique(['presentation_attempt_id', 'panelist_user_id'], 'evaluation_submissions_attempt_panelist_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_submissions');
    }
};
