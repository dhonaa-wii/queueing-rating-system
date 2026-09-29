<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_submission_id')->constrained('evaluation_submissions');
            $table->foreignId('evaluation_criterion_id')->constrained('evaluation_criteria');
            $table->foreignId('student_id')->nullable()->constrained('students');
            $table->foreignId('proposed_title_id')->nullable()->constrained('proposed_titles');
            $table->decimal('score', 10, 2);
            $table->decimal('weighted_score', 12, 4)->nullable();
            $table->text('remarks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
    }
};
