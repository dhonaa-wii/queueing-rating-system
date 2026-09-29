<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentation_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('semester_id')->constrained('semesters');
            $table->foreignId('program_id')->constrained('programs');
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('subject_or_research_type', 150)->nullable();
            $table->foreignId('category_status_id')->constrained('category_statuses');
            $table->foreignId('presentation_mode_id')->constrained('presentation_modes');
            $table->tinyInteger('required_proposed_title_count')->unsigned()->nullable();
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->tinyInteger('maximum_members')->unsigned();
            $table->boolean('technical_adviser_required')->default(false);
            $table->boolean('research_track_required')->default(false);
            $table->boolean('public_queue_visible')->default(true);
            $table->timestamps();
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentation_categories');
    }
};
