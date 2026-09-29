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
            ['code' => 'PAYMENT_VERIFICATION_CONCERN', 'name' => 'Payment Verification Concern', 'is_active' => true],
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
        ];

        foreach ($reasons as $reason) {
            AdjustmentReason::updateOrCreate(['code' => $reason['code']], $reason);
        }
    }
}
