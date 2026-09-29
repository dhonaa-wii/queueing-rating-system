<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admin accounts can be deleted permanently (user-directed 2026-09-30). The
 * "done by" columns that point at users — who created a category, approved a
 * queue change, scheduled a group, wrote an audit entry — used to require a
 * user and refuse (RESTRICT) a delete. They now allow NULL and clear
 * themselves (ON DELETE SET NULL): the record stays, it just no longer names
 * the person who was removed.
 *
 * Columns that identify the *subject* of a row (a panelist's own seat,
 * evaluation or connection; a profile's owner) are left alone — those rows
 * belong to that user, not merely mention them.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'attempt_decisions' => ['recorded_by', 'finalized_by'],
        'attempt_panel_assignments' => ['assigned_by'],
        'attempt_rating_summaries' => ['finalized_by'],
        'attempt_requirements' => ['reviewed_by'],
        'attempt_schedules' => ['scheduled_by'],
        'audit_logs' => ['user_id'],
        'category_announcements' => ['created_by'],
        'category_evaluation_forms' => ['assigned_by'],
        'category_rooms' => ['added_by'],
        'end_of_day_processing_logs' => ['processed_by'],
        'evaluation_forms' => ['created_by'],
        'evaluation_form_versions' => ['created_by'],
        'evaluation_letterheads' => ['updated_by'],
        'evaluation_submissions' => ['reopened_by'],
        'panelist_profiles' => ['registered_by'],
        'panel_substitution_requests' => ['requested_by', 'reviewed_by'],
        'payment_verifications' => ['initially_checked_by', 'referred_by', 'resolved_by'],
        'presentation_actions' => ['performed_by'],
        'presentation_attempts' => ['created_by'],
        'presentation_categories' => ['created_by'],
        'presentation_date_rooms' => ['added_by', 'closure_requested_by', 'closed_by'],
        'presentation_events' => ['started_by', 'ended_by'],
        'presentation_pauses' => ['paused_by', 'resumed_by'],
        'queue_adjustments' => ['approved_by'],
        'room_sessions' => ['ended_by_user_id'],
        'room_session_accounts' => ['generated_by', 'credentials_reset_by', 'deactivated_by'],
        'room_session_notices' => ['performed_by'],
        'system_settings' => ['updated_by'],
    ];

    public function up(): void
    {
        $this->rebuild('SET NULL', true);
    }

    /** Back to RESTRICT. The columns stay nullable: rows may now hold NULL. */
    public function down(): void
    {
        $this->rebuild('RESTRICT', false);
    }

    private function rebuild(string $onDelete, bool $makeNullable): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $constraint = DB::selectOne(
                    "SELECT k.CONSTRAINT_NAME AS name FROM information_schema.KEY_COLUMN_USAGE k
                     WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ?
                       AND k.REFERENCED_TABLE_NAME = 'users'",
                    [$table, $column]
                );

                if (! $constraint) {
                    continue;
                }

                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->name}`");

                if ($makeNullable) {
                    DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED NULL");
                }

                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint->name}` FOREIGN KEY (`{$column}`) REFERENCES `users` (`id`) ON DELETE {$onDelete}");
            }
        }
    }
};
