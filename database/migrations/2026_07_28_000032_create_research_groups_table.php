<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_reference', 50)->unique();
            $table->foreignId('category_id')->constrained('presentation_categories');
            $table->string('current_project_title', 255)->nullable();
            $table->foreignId('research_track_id')->nullable()->constrained('research_tracks');
            $table->string('technical_adviser_name', 200)->nullable();
            $table->dateTime('registered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_groups');
    }
};
