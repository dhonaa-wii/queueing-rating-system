<?php

namespace Database\Seeders;

use App\Models\EventDateStatus;
use Illuminate\Database\Seeder;

class EventDateStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'PLANNED', 'name' => 'Planned', 'is_terminal' => false],
            ['code' => 'STANDBY', 'name' => 'Standby', 'is_terminal' => false],
            ['code' => 'ACTIVE', 'name' => 'Active', 'is_terminal' => false],
            ['code' => 'COMPLETED', 'name' => 'Completed', 'is_terminal' => true],
            ['code' => 'CANCELLED', 'name' => 'Cancelled', 'is_terminal' => true],
        ];

        foreach ($statuses as $status) {
            EventDateStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
