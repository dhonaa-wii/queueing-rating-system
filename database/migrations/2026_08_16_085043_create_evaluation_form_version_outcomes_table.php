<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_form_version_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_version_id');
            $table->foreignId('presentation_outcome_id')->constrained('presentation_outcomes');
            $table->unsignedInteger('sort_order');

            $table->foreign('evaluation_form_version_id', 'eval_form_version_outcomes_version_fk')
                ->references('id')->on('evaluation_form_versions')->cascadeOnDelete();
            $table->unique(['evaluation_form_version_id', 'presentation_outcome_id'], 'eval_form_version_outcomes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_form_version_outcomes');
    }
};
