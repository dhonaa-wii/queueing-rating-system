<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_categories', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('semester_id')->constrained('programs');
        });
    }

    public function down(): void
    {
        Schema::table('presentation_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
    }
};
