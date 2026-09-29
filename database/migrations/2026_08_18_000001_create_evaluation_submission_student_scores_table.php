<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sample evaluation sheet's "Individual (Raw Score)" column is a
 * holistic per-researcher number the panel fills in directly — not tied to
 * any single criterion, unlike evaluation_scores (which stays reserved for
 * a future criterion-level INDIVIDUAL_STUDENT scope per its own doc
 * comment). Separate table rather than relaxing evaluation_scores.
 * evaluation_criterion_id to nullable, so "a per-criterion score" and "a
 * per-student holistic score" stay two distinct, unambiguous concepts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_submission_student_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_submission_id');
            $table->foreign('evaluation_submission_id', 'eval_sub_student_scores_submission_fk')
                ->references('id')->on('evaluation_submissions')->cascadeOnDelete();
            $table->foreignId('student_id');
            $table->foreign('student_id', 'eval_sub_student_scores_student_fk')
                ->references('id')->on('students');
            $table->decimal('score', 5, 2)->nullable();
            $table->unique(['evaluation_submission_id', 'student_id'], 'eval_sub_student_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_submission_student_scores');
    }
};
