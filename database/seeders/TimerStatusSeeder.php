<?php

namespace Database\Seeders;

use App\Models\TimerStatus;
use Illuminate\Database\Seeder;

class TimerStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'NOT_STARTED', 'name' => 'Not Started'],
            ['code' => 'WITHIN_TIME', 'name' => 'Within Time'],
            ['code' => 'PAUSED', 'name' => 'Paused'],
            ['code' => 'EXTENDED', 'name' => 'Extended'],
            ['code' => 'COMPLETED', 'name' => 'Completed'],
        ];

        foreach ($statuses as $status) {
            TimerStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
