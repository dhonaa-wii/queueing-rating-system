<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groups may choose their own panel at registration when the category allows
 * it (user-directed 2026-10-11). The picks are kept per group, in order — the
 * first pick is the Chair — and become the group's actual panel once it is
 * placed in the queue (PanelAssignmentService::applyGroupChoices()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentation_categories', function (Blueprint $table) {
            $table->boolean('students_choose_panelists')->default(false)->after('panelist_count');
        });

        Schema::create('research_group_panelist_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_group_id')->constrained('research_groups')->cascadeOnDelete();
            $table->foreignId('panelist_user_id')->constrained('users');
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->unique(['research_group_id', 'panelist_user_id'], 'rg_panelist_choices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_group_panelist_choices');

        Schema::table('presentation_categories', function (Blueprint $table) {
            $table->dropColumn('students_choose_panelists');
        });
    }
};
