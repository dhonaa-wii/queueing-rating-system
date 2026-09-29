<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_submissions', function (Blueprint $table) {
            $table->foreignId('presentation_outcome_id')->nullable()->after('remarks')->constrained('presentation_outcomes');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('presentation_outcome_id');
        });
    }
};
