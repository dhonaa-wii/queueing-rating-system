<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-directed 2026-09-13: "remove the transition between groups and
 * warning time before ends." Both were admin-configurable on Presentation
 * Setup's Schedules tab and neither is wanted any more, so the columns go
 * rather than being left unused — same posture as the 2026-08-04
 * restructure and this codebase's standing "don't leave stale schema around
 * just in case" rule.
 *
 * - transition_minutes padded every queue slot: the generator, the queue
 *   renumberer and both live forecast services all added it after each
 *   group. Groups are now laid out back to back.
 * - warning_minutes drove only the amber "nearly out of time" band on the
 *   running countdown. The timer keeps its green (time remaining) and red
 *   (extended past the limit) states.
 *
 * down() restores the columns at their original definitions, but the values
 * are gone — a rollback leaves every category at NULL/0, which is the same
 * as not configuring them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_schedule_settings', function (Blueprint $table) {
            $table->dropColumn(['warning_minutes', 'transition_minutes']);
        });
    }

    public function down(): void
    {
        Schema::table('category_schedule_settings', function (Blueprint $table) {
            $table->smallInteger('warning_minutes')->unsigned()->nullable()->after('duration_minutes');
            $table->smallInteger('transition_minutes')->unsigned()->default(0)->after('warning_minutes');
        });
    }
};
