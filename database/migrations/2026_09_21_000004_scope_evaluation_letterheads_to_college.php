<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * User-directed 2026-09-21: "the letterhead is only tied to the college ...
 * if other college different letterhead config." The table was a singleton
 * (every reader did EvaluationLetterhead::first()); it now holds one row per
 * college, so a second college configures its own logos and header lines
 * without touching anyone else's.
 *
 * The existing row is backfilled from its last editor's own college
 * (administrator_profiles.college_id) — the same "whose registry owns this
 * record" rule §2.26 uses for panelists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_letterheads', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->after('id')
                ->constrained('colleges')->nullOnDelete();
        });

        DB::statement('
            UPDATE evaluation_letterheads el
            JOIN administrator_profiles ap ON ap.user_id = el.updated_by
            SET el.college_id = ap.college_id
            WHERE el.college_id IS NULL
        ');

        Schema::table('evaluation_letterheads', function (Blueprint $table) {
            $table->unique('college_id');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_letterheads', function (Blueprint $table) {
            $table->dropUnique(['college_id']);
            $table->dropConstrainedForeignId('college_id');
        });
    }
};
