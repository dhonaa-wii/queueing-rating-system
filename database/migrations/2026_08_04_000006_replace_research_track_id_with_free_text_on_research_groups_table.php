<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('research_track_id');
            $table->string('research_track_name', 150)->nullable()->after('current_project_title');
        });
    }

    public function down(): void
    {
        Schema::table('research_groups', function (Blueprint $table) {
            $table->dropColumn('research_track_name');
            $table->foreignId('research_track_id')->nullable()->after('current_project_title')->constrained('research_tracks');
        });
    }
};
