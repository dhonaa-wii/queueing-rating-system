<?php

namespace Database\Seeders;

use App\Models\QueueAdjustmentType;
use Illuminate\Database\Seeder;

class QueueAdjustmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'REORDER', 'name' => 'Reorder'],
            ['code' => 'MOVE_TO_END', 'name' => 'Move to End'],
            ['code' => 'DEFER', 'name' => 'Defer'],
            ['code' => 'REINSERT', 'name' => 'Reinsert'],
            ['code' => 'TRANSFER_ROOM', 'name' => 'Transfer Room'],
            ['code' => 'CHANGE_DATE', 'name' => 'Change Date'],
            ['code' => 'TEMPORARY_REMOVE', 'name' => 'Temporary Remove'],
            ['code' => 'RESTORE', 'name' => 'Restore'],
            ['code' => 'MARK_ABSENT', 'name' => 'Mark Absent'],
            ['code' => 'INITIAL_ROOM_DISTRIBUTION', 'name' => 'Initial Room Distribution'],
            ['code' => 'ROOM_ADDED_REDISTRIBUTION', 'name' => 'Room Added Redistribution'],
            ['code' => 'ROOM_CLOSURE_REDISTRIBUTION', 'name' => 'Room Closure Redistribution'],
            ['code' => 'ROOM_REMOVED_REDISTRIBUTION', 'name' => 'Room Removed Redistribution'],
            ['code' => 'CARRY_OVER_NEXT_DAY', 'name' => 'Carry Over to Next Day'],
        ];

        foreach ($types as $type) {
            QueueAdjustmentType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
