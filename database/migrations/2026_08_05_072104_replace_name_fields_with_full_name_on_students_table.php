<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('full_name', 200)->after('student_number');
        });

        DB::table('students')->orderBy('id')->each(function ($student) {
            $name = trim(implode(' ', array_filter([
                $student->first_name,
                $student->middle_name,
                $student->last_name,
                $student->suffix,
            ])));

            DB::table('students')->where('id', $student->id)->update(['full_name' => $name]);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'middle_name', 'last_name', 'suffix']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('student_number');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('suffix', 20)->nullable()->after('last_name');
        });

        DB::table('students')->orderBy('id')->each(function ($student) {
            DB::table('students')->where('id', $student->id)->update(['first_name' => $student->full_name]);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }
};
