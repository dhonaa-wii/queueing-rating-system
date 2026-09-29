<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_scale_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_version_id')->constrained('evaluation_form_versions')->cascadeOnDelete();
            $table->decimal('value', 4, 1);
            $table->string('label', 100);

            $table->unique(['evaluation_form_version_id', 'value'], 'evaluation_scale_labels_version_value_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scale_labels');
    }
};
