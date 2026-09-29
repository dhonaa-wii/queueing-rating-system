<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('administrator_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
    }

    public function down(): void
    {
        Schema::table('administrator_profiles', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('user_id')->constrained('programs')->nullOnDelete();
        });
    }
};
