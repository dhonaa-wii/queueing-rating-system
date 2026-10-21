<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A group that registers while its category has no date or room yet is
     * still placed in the queue (user-directed 2026-10-21): it gets its
     * attempt, schedule and queue number in strategy order, with no room and
     * no time — "to be scheduled" — and is moved into a room as soon as one
     * exists (QueueGenerationService::appendUnqueuedGroups()).
     */
    public function up(): void
    {
        Schema::table('attempt_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('presentation_date_room_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('attempt_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('presentation_date_room_id')->nullable(false)->change();
        });
    }
};
