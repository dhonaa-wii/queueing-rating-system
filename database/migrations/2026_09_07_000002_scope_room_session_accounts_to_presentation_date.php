<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * User-directed 2026-09-07: room_session_accounts used to be one reusable
 * login per (category, room) generated the moment a room's panelist_count
 * was set — carrying across the whole category's lifetime. New rule: an
 * account is generated only when its day actually starts
 * (EventActivationService::start()) and removed the moment that day ends/
 * completes/cancels — a fresh account every day, never reused. This scopes
 * the table to the specific presentation_date it belongs to instead of
 * just the category, and the old category+room uniqueness becomes
 * date+room uniqueness (a room can only ever have one live account per
 * day, but a new one every new day).
 *
 * The one pre-existing row in the dev DB (id 1, "Room 1" for category 1)
 * predates any specific day under the old category-wide model and doesn't
 * map onto one — no event is currently ACTIVE (confirmed before writing
 * this migration), so it's safe to drop rather than guess which day it
 * belonged to; matches this codebase's own precedent of clearing genuinely
 * trivial/stale dev data during a redesign rather than migrating it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('room_session_accounts')->delete();

        Schema::table('room_session_accounts', function (Blueprint $table) {
            // presentation_category_id's own FK needs *some* index to rely
            // on — the composite unique(category_id, room_name) being
            // dropped below was the only one covering it, so a plain index
            // has to exist first or MySQL refuses the drop (error 1553).
            $table->index('presentation_category_id', 'room_session_accounts_category_id_index');
            $table->dropUnique(['presentation_category_id', 'room_name']);
            $table->foreignId('presentation_date_id')->after('presentation_category_id')->constrained('presentation_dates')->cascadeOnDelete();
            $table->unique(['presentation_date_id', 'room_name']);
        });
    }

    public function down(): void
    {
        Schema::table('room_session_accounts', function (Blueprint $table) {
            $table->dropUnique(['presentation_date_id', 'room_name']);
            $table->dropConstrainedForeignId('presentation_date_id');
            $table->unique(['presentation_category_id', 'room_name']);
            $table->dropIndex('room_session_accounts_category_id_index');
        });
    }
};
