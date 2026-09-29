<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * READY_FOR_QUEUE was a separate category_statuses row for "registration
 * closed, setup complete" — merged into SETUP_INCOMPLETE (user-directed
 * 2026-08-25) since the two only ever differed by whether queue generation
 * was currently allowed, which PresentationCategory::deriveStatus() now
 * always derives as SETUP_INCOMPLETE and QueueGenerationService checks live
 * via setupCompletionStatus() instead of a per-status flag. Any category
 * still pointing at the old row is repointed before it's deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        $readyId = DB::table('category_statuses')->where('code', 'READY_FOR_QUEUE')->value('id');
        $setupIncompleteId = DB::table('category_statuses')->where('code', 'SETUP_INCOMPLETE')->value('id');

        if ($readyId && $setupIncompleteId) {
            DB::table('presentation_categories')
                ->where('category_status_id', $readyId)
                ->update(['category_status_id' => $setupIncompleteId]);

            DB::table('category_statuses')->where('id', $readyId)->delete();
        }
    }

    public function down(): void
    {
        DB::table('category_statuses')->updateOrInsert(
            ['code' => 'READY_FOR_QUEUE'],
            [
                'name' => 'Ready for Queue Generation',
                'allows_registration' => false,
                'allows_queue_generation' => true,
                'is_terminal' => false,
            ]
        );
    }
};
