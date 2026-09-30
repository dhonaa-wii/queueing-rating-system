<?php

namespace Database\Seeders;

use App\Models\AdjustmentReason;
use Illuminate\Database\Seeder;

class AdjustmentReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['code' => 'LATE', 'name' => 'Late', 'is_active' => true],
                        ['code' => 'GROUP_NOT_READY', 'name' => 'Group Not Ready', 'is_active' => true],
            ['code' => 'TECHNICAL_CONCERN', 'name' => 'Technical Concern', 'is_active' => true],
            ['code' => 'ADMINISTRATIVE_CONCERN', 'name' => 'Administrative Concern', 'is_active' => true],
            ['code' => 'ROOM_ADDED', 'name' => 'Room Added', 'is_active' => true],
            ['code' => 'ROOM_PENDING_CLOSURE', 'name' => 'Room Pending Closure', 'is_active' => true],
            ['code' => 'ROOM_CLOSED', 'name' => 'Room Closed', 'is_active' => true],
            ['code' => 'ROOM_REMOVED', 'name' => 'Room Removed', 'is_active' => true],
            ['code' => 'LIVE_CAPACITY_ADJUSTMENT', 'name' => 'Live Capacity Adjustment', 'is_active' => true],
            ['code' => 'UNRESOLVED_DEFERRED_END_OF_DAY', 'name' => 'Deferred — Unresolved at End of Day', 'is_active' => true],
            ['code' => 'UNFINISHED_END_OF_DAY', 'name' => 'Unfinished at End of Day', 'is_active' => true],
            ['code' => 'DATE_REMOVED', 'name' => 'Presentation Date Removed', 'is_active' => true],
            ['code' => 'MOVED_TO_EARLIER_DATE', 'name' => 'Moved to an Earlier Date', 'is_active' => true],
            ['code' => 'OTHER', 'name' => 'Other', 'is_active' => true],

            // The Defer dropdown's own list, in this order (2026-09-30).
            ['code' => 'NOT_PRESENT_WHEN_CALLED', 'name' => 'Not Present When Called', 'is_active' => true, 'is_defer_reason' => true, 'sort_order' => 1],
            ['code' => 'INCOMPLETE_GROUP', 'name' => 'Incomplete Group (Member Absent)', 'is_active' => true, 'is_defer_reason' => true, 'sort_order' => 2],
            ['code' => 'NOT_READY_TO_PRESENT', 'name' => 'Not Ready to Present', 'is_active' => true, 'is_defer_reason' => true, 'sort_order' => 3],
            ['code' => 'SYSTEM_NOT_WORKING', 'name' => 'System / Prototype Not Working', 'is_active' => true, 'is_defer_reason' => true, 'sort_order' => 4],
            ['code' => 'INCOMPLETE_REQUIREMENTS', 'name' => 'Incomplete Requirements', 'is_active' => true, 'is_defer_reason' => true, 'sort_order' => 5],
            ['code' => 'PAYMENT_VERIFICATION_CONCERN', 'name' => 'Payment Not Verified', 'is_active' => true, 'is_defer_reason' => true, 'sort_order' => 6],
            ['code' => 'TECHNICAL_ADVISER_ABSENT', 'name' => 'Technical Adviser Absent', 'is_active' => true, 'is_defer_reason' => true, 'sort_order' => 7],
            ['code' => 'MEDICAL_EMERGENCY', 'name' => 'Medical / Emergency', 'is_active' => true, 'is_defer_reason' => true, 'sort_order' => 8],
        ];

        foreach ($reasons as $reason) {
            AdjustmentReason::updateOrCreate(['code' => $reason['code']], $reason);
        }
    }
}
