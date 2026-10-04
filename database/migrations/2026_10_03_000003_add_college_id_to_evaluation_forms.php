<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User-directed 2026-10-03: evaluation forms belong to one college, like
     * categories and panelists (AdminCollege). Existing forms take the college
     * of the Admin who created them, else of a category that uses them, else
     * the first college (CCIS on the live data).
     */
    public function up(): void
    {
        Schema::table('evaluation_forms', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->after('id')->constrained('colleges')->nullOnDelete();
        });

        DB::statement('UPDATE evaluation_forms f JOIN administrator_profiles ap ON ap.user_id = f.created_by SET f.college_id = ap.college_id WHERE f.college_id IS NULL');

        DB::statement('UPDATE evaluation_forms f
            JOIN (SELECT v.evaluation_form_id AS form_id, MIN(c.college_id) AS college_id
                  FROM category_evaluation_forms cef
                  JOIN evaluation_form_versions v ON v.id = cef.evaluation_form_version_id
                  JOIN presentation_categories c ON c.id = cef.category_id
                  WHERE c.college_id IS NOT NULL
                  GROUP BY v.evaluation_form_id) x ON x.form_id = f.id
            SET f.college_id = x.college_id WHERE f.college_id IS NULL');

        $first = DB::table('colleges')->orderBy('id')->value('id');
        if ($first) {
            DB::table('evaluation_forms')->whereNull('college_id')->update(['college_id' => $first]);
        }
    }

    public function down(): void
    {
        Schema::table('evaluation_forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('college_id');
        });
    }
};
