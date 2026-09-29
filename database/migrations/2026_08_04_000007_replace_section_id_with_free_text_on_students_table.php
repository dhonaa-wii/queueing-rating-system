<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('section_id');
            $table->string('section_name', 50)->nullable()->after('suffix');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('section_name');
            $table->foreignId('section_id')->after('suffix')->constrained('sections');
        });
    }
};
