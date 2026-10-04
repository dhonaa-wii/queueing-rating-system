<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-track evaluation forms (user-directed 2026-10-04): when a category
 * requires a research track, each track is assigned its own evaluation form,
 * and a group is evaluated with its leader's track's form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_research_tracks', function (Blueprint $table) {
            $table->foreignId('evaluation_form_version_id')
                ->nullable()
                ->after('name')
                ->constrained('evaluation_form_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('category_research_tracks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluation_form_version_id');
        });
    }
};
