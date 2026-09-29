<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->string('department_or_program', 150)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('panelist_profiles', function (Blueprint $table) {
            $table->dropColumn('department_or_program');
            $table->foreignId('program_id')->nullable()->after('user_id')->constrained('programs')->nullOnDelete();
        });
    }
};
