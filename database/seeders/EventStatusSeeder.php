<?php

namespace Database\Seeders;

use App\Models\EventStatus;
use Illuminate\Database\Seeder;

class EventStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'READY', 'name' => 'Ready', 'is_terminal' => false],
            ['code' => 'ACTIVE', 'name' => 'Active', 'is_terminal' => false],
            ['code' => 'COMPLETED', 'name' => 'Completed', 'is_terminal' => true],
            ['code' => 'CANCELLED', 'name' => 'Cancelled', 'is_terminal' => true],
        ];

        foreach ($statuses as $status) {
            EventStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
