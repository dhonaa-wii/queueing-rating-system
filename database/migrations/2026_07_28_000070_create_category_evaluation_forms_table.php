<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_evaluation_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('presentation_categories');
            $table->foreignId('evaluation_form_version_id')->constrained('evaluation_form_versions');
            $table->dateTime('effective_from');
            $table->dateTime('effective_until')->nullable();
            $table->foreignId('assigned_by')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_evaluation_forms');
    }
};
