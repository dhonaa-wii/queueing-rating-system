<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * User-directed 2026-09-15: "Public queue visibility" is no longer a
     * per-category checkbox — the queue is always public. The column stays
     * (same treatment as the other always-on queue options); existing rows
     * are brought in line.
     */
    public function up(): void
    {
        DB::table('presentation_categories')->update(['public_queue_visible' => true]);
    }

    public function down(): void
    {
        // Previous per-category choices are not recoverable; nothing to undo.
    }
};
