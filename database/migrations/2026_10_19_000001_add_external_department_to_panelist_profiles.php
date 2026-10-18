<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External panelists (user-directed 2026-10-19). An internal panelist's
 * department is the registering Admin's college (college_id); an external
 * one still belongs to that college's registry (college_id unchanged, so they
 * can sit on its panels) but comes from another department, typed by the
 * Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->boolean('is_external')->default(false)->after('college_id');
            $table->string('external_department', 150)->nullable()->after('is_external');
        });
    }

    public function down(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_external', 'external_department']);
        });
    }
};
