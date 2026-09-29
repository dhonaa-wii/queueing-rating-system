<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_version_id')->constrained('evaluation_form_versions');
            $table->foreignId('parent_criterion_id')->nullable()->constrained('evaluation_criteria');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->foreignId('criterion_scope_id')->constrained('criterion_scopes');
            $table->decimal('weight', 8, 4)->nullable();
            $table->decimal('maximum_score', 10, 2);
            $table->integer('sort_order')->unsigned();
            $table->boolean('is_required')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
    }
};
