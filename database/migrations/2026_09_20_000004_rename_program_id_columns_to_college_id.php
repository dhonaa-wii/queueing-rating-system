<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User-directed 2026-09-20, same batch as
     * 2026_09_20_000003_rename_programs_table_to_colleges — every
     * program_id FK column pointing at the renamed colleges table becomes
     * college_id. Done as add-copy-drop-readd rather than Schema::
     * renameColumn(): this environment's MariaDB (10.4.32) predates
     * native RENAME COLUMN support (10.5.2+) and doctrine/dbal, Laravel's
     * fallback for that, isn't installed — this approach works on any
     * MySQL/MariaDB version and preserves every existing row's value.
     */
    private const TABLES = [
        'administrator_profiles' => ['nullOnDelete' => true],
        'panelist_profiles' => ['nullOnDelete' => true],
        'presentation_categories' => ['nullOnDelete' => false],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $options) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('college_id')->nullable()->after('program_id');
            });

            DB::statement("UPDATE {$table} SET college_id = program_id");

            Schema::table($table, function (Blueprint $blueprint) use ($table, $options) {
                $blueprint->dropForeign(['program_id']);
                $blueprint->dropColumn('program_id');

                $foreign = $blueprint->foreign('college_id')->references('id')->on('colleges');
                if ($options['nullOnDelete']) {
                    $foreign->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $options) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedBigInteger('program_id')->nullable()->after('college_id');
            });

            DB::statement("UPDATE {$table} SET program_id = college_id");

            Schema::table($table, function (Blueprint $blueprint) use ($options) {
                $blueprint->dropForeign(['college_id']);
                $blueprint->dropColumn('college_id');

                $foreign = $blueprint->foreign('program_id')->references('id')->on('colleges');
                if ($options['nullOnDelete']) {
                    $foreign->nullOnDelete();
                }
            });
        }
    }
};
