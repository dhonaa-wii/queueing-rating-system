<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_group_id')->constrained('research_groups');
            $table->smallInteger('attempt_number')->unsigned();
            $table->foreignId('attempt_type_id')->constrained('attempt_types');
            $table->foreignId('previous_attempt_id')->nullable()->constrained('presentation_attempts');
            $table->foreignId('presentation_status_id')->constrained('presentation_statuses');
            $table->foreignId('final_outcome_id')->nullable()->constrained('presentation_outcomes');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('completed_at')->nullable();

            $table->unique(['research_group_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_attempts');
    }
};
