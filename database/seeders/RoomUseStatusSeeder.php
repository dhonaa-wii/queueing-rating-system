<?php

namespace Database\Seeders;

use App\Models\RoomUseStatus;
use Illuminate\Database\Seeder;

class RoomUseStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'ACTIVE', 'name' => 'Active', 'is_accepting_queue' => true, 'is_terminal' => false],
            ['code' => 'PENDING_CLOSURE', 'name' => 'Pending Closure', 'is_accepting_queue' => false, 'is_terminal' => false],
            ['code' => 'CLOSED', 'name' => 'Closed', 'is_accepting_queue' => false, 'is_terminal' => true],
            ['code' => 'REMOVED', 'name' => 'Removed', 'is_accepting_queue' => false, 'is_terminal' => true],
        ];

        foreach ($statuses as $status) {
            RoomUseStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
