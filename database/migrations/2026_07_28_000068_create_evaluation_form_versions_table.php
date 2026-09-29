<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_form_id')->constrained('evaluation_forms');
            $table->smallInteger('version_number')->unsigned();
            $table->foreignId('status_id')->constrained('form_version_statuses');
            $table->foreignId('scoring_method_id')->constrained('scoring_methods');
            $table->decimal('total_weight', 8, 4)->nullable();
            $table->decimal('maximum_total_score', 10, 2)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('retired_at')->nullable();

            $table->unique(['evaluation_form_id', 'version_number'], 'evaluation_form_versions_form_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_form_versions');
    }
};
