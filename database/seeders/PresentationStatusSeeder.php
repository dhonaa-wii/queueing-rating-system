<?php

namespace Database\Seeders;

use App\Models\PresentationStatus;
use Illuminate\Database\Seeder;

class PresentationStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'SCHEDULED', 'name' => 'Scheduled', 'is_terminal' => false],
            ['code' => 'QUEUED', 'name' => 'Queued', 'is_terminal' => false],
            ['code' => 'READY_NEXT', 'name' => 'Ready Next', 'is_terminal' => false],
            ['code' => 'CALLED', 'name' => 'Called', 'is_terminal' => false],
            ['code' => 'DEFERRED', 'name' => 'Deferred', 'is_terminal' => false],
            ['code' => 'ONGOING', 'name' => 'Ongoing', 'is_terminal' => false],
            ['code' => 'PAUSED', 'name' => 'Paused', 'is_terminal' => false],
            ['code' => 'COMPLETED', 'name' => 'Completed', 'is_terminal' => true],
            ['code' => 'ABSENT', 'name' => 'Absent', 'is_terminal' => true],
            ['code' => 'CANCELLED', 'name' => 'Cancelled', 'is_terminal' => true],
            ['code' => 'RESCHEDULED', 'name' => 'Rescheduled', 'is_terminal' => false],
        ];

        foreach ($statuses as $status) {
            PresentationStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
