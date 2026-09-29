<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempt_rating_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presentation_attempt_id')->unique()->constrained('presentation_attempts');
            $table->smallInteger('required_evaluations')->unsigned();
            $table->smallInteger('received_evaluations')->unsigned();
            $table->decimal('final_group_score', 12, 4)->nullable();
            $table->decimal('final_rating', 12, 4)->nullable();
            $table->string('calculation_rule_version', 50)->nullable();
            $table->dateTime('calculated_at');
            $table->foreignId('finalized_by')->nullable()->constrained('users');
            $table->dateTime('finalized_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_rating_summaries');
    }
};
