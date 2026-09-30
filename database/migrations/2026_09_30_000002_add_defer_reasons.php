<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Defer has its own reason list (user-directed 2026-09-30), based on why
 * schools actually hold a group back. Every reason dropdown used to show the
 * whole adjustment_reasons table — including the ones the system records for
 * its own moves (Room Closed, Unfinished at End of Day, …). Now:
 *   - is_defer_reason marks the reasons the Defer dropdowns show, in
 *     sort_order;
 *   - the other dropdowns (Move, Transfer, Reinsert, Pause) keep the rest.
 *
 * "Late" is not one of them: the panel can't tell a late group from an
 * absent one, so the reason is "Not Present When Called". Existing rows are
 * never deleted — past defers keep the reason they were recorded with.
 */
return new class extends Migration
{
    private const DEFER_REASONS = [
        ['code' => 'NOT_PRESENT_WHEN_CALLED', 'name' => 'Not Present When Called'],
        ['code' => 'INCOMPLETE_GROUP', 'name' => 'Incomplete Group (Member Absent)'],
        ['code' => 'NOT_READY_TO_PRESENT', 'name' => 'Not Ready to Present'],
        ['code' => 'SYSTEM_NOT_WORKING', 'name' => 'System / Prototype Not Working'],
        ['code' => 'INCOMPLETE_REQUIREMENTS', 'name' => 'Incomplete Requirements'],
        // Reuses the existing payment row, renamed: its code is referenced in code.
        ['code' => 'PAYMENT_VERIFICATION_CONCERN', 'name' => 'Payment Not Verified'],
        ['code' => 'TECHNICAL_ADVISER_ABSENT', 'name' => 'Technical Adviser Absent'],
        ['code' => 'MEDICAL_EMERGENCY', 'name' => 'Medical / Emergency'],
    ];

    public function up(): void
    {
        Schema::table('adjustment_reasons', function (Blueprint $table) {
            $table->boolean('is_defer_reason')->default(false)->after('name');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_defer_reason');
        });

        foreach (self::DEFER_REASONS as $i => $reason) {
            DB::table('adjustment_reasons')->updateOrInsert(
                ['code' => $reason['code']],
                ['name' => $reason['name'], 'is_active' => true, 'is_defer_reason' => true, 'sort_order' => $i + 1]
            );
        }
    }

    public function down(): void
    {
        DB::table('adjustment_reasons')->where('code', 'PAYMENT_VERIFICATION_CONCERN')->update(['name' => 'Payment Verification Concern']);

        // New rows are removed only if nothing was recorded with them.
        foreach (self::DEFER_REASONS as $reason) {
            if ($reason['code'] === 'PAYMENT_VERIFICATION_CONCERN') {
                continue;
            }

            $id = DB::table('adjustment_reasons')->where('code', $reason['code'])->value('id');
            $used = $id && (DB::table('queue_adjustments')->where('reason_id', $id)->exists()
                || DB::table('presentation_actions')->where('reason_id', $id)->exists()
                || DB::table('presentation_pauses')->where('reason_id', $id)->exists()
                || DB::table('attempt_schedules')->where('change_reason_id', $id)->exists());

            if ($id && ! $used) {
                DB::table('adjustment_reasons')->where('id', $id)->delete();
            }
        }

        Schema::table('adjustment_reasons', function (Blueprint $table) {
            $table->dropColumn(['is_defer_reason', 'sort_order']);
        });
    }
};
