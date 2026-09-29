<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_form_version_presentation_modes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_version_id');
            $table->foreignId('presentation_mode_id');

            $table->foreign('evaluation_form_version_id', 'eval_form_version_modes_version_fk')
                ->references('id')->on('evaluation_form_versions')->cascadeOnDelete();
            $table->foreign('presentation_mode_id', 'eval_form_version_modes_mode_fk')
                ->references('id')->on('presentation_modes');
            $table->unique(['evaluation_form_version_id', 'presentation_mode_id'], 'eval_form_version_modes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_form_version_presentation_modes');
    }
};
