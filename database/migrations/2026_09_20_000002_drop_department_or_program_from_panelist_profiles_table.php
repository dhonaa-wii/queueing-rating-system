<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User-directed 2026-09-20: the free-text "Department / Program" field
     * on a panelist's profile is removed entirely — it duplicated the
     * (now-restored) `program_id` tie to the registering Admin's own
     * Program in every real case, so the panelist's affiliation is fully
     * described by that Program going forward.
     */
    public function up(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->dropColumn('department_or_program');
        });
    }

    public function down(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->string('department_or_program', 150)->nullable()->after('program_id');
        });
    }
};
