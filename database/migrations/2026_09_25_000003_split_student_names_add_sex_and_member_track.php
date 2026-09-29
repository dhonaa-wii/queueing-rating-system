<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUFFIXES = ['jr', 'sr', 'ii', 'iii', 'iv', 'v'];

    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('last_name', 100)->nullable()->after('student_number');
            $table->string('first_name', 100)->nullable()->after('last_name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('sex', 10)->nullable()->after('middle_name');
            $table->string('research_track_name', 150)->nullable()->after('section_name');
        });

        DB::table('students')->orderBy('id')->each(function ($student) {
            [$last, $first, $middle] = $this->splitFullName((string) $student->full_name);

            DB::table('students')->where('id', $student->id)->update([
                'last_name' => $last,
                'first_name' => $first,
                'middle_name' => $middle,
            ]);
        });

        // The research track used to be one value per group; it is now
        // recorded per member, so every member starts with their group's.
        DB::statement('UPDATE students s JOIN research_groups g ON g.id = s.research_group_id SET s.research_track_name = g.research_track_name');

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });

        Schema::table('research_groups', function (Blueprint $table) {
            $table->dropColumn('research_track_name');
        });

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('sex', 10)->nullable()->after('suffix');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('sex');
        });

        Schema::table('research_groups', function (Blueprint $table) {
            $table->string('research_track_name', 150)->nullable()->after('current_project_title');
        });

        DB::statement('UPDATE research_groups g SET g.research_track_name = (SELECT s.research_track_name FROM students s WHERE s.research_group_id = g.id AND s.is_leader = 1 LIMIT 1)');

        Schema::table('students', function (Blueprint $table) {
            $table->string('full_name', 200)->after('student_number');
        });

        DB::table('students')->orderBy('id')->each(function ($student) {
            $name = trim(implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name])));

            DB::table('students')->where('id', $student->id)->update(['full_name' => $name]);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['last_name', 'first_name', 'middle_name', 'sex', 'research_track_name']);
        });
    }

    /** Best-effort split of the old single name into [last, first, middle]. */
    private function splitFullName(string $fullName): array
    {
        $fullName = trim($fullName);

        if (str_contains($fullName, ',')) {
            [$last, $rest] = array_map('trim', explode(',', $fullName, 2));
            $parts = preg_split('/\s+/', $rest, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return [$last, $parts[0] ?? null, count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null];
        }

        $parts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $suffix = null;

        if (count($parts) > 2 && in_array(strtolower(rtrim((string) end($parts), '.')), self::SUFFIXES, true)) {
            $suffix = array_pop($parts);
        }

        if (count($parts) === 0) {
            return [null, null, null];
        }

        if (count($parts) === 1) {
            return [$parts[0], $parts[0], null];
        }

        $last = array_pop($parts).($suffix ? ' '.$suffix : '');
        $first = array_shift($parts);

        return [$last, $first, $parts ? implode(' ', $parts) : null];
    }
};
