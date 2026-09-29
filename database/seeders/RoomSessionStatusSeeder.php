<?php

namespace Database\Seeders;

use App\Models\RoomSessionStatus;
use Illuminate\Database\Seeder;

class RoomSessionStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'WAITING', 'name' => 'Waiting'],
            ['code' => 'AVAILABLE', 'name' => 'Available'],
            ['code' => 'ACTIVE', 'name' => 'Active'],
            ['code' => 'PAUSED', 'name' => 'Paused'],
            ['code' => 'BREAK', 'name' => 'Break'],
            ['code' => 'PENDING_CLOSURE', 'name' => 'Pending Closure'],
            ['code' => 'FINISHED', 'name' => 'Finished'],
            ['code' => 'CLOSED', 'name' => 'Closed'],
        ];

        foreach ($statuses as $status) {
            RoomSessionStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
