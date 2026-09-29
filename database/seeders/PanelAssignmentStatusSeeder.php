<?php

namespace Database\Seeders;

use App\Models\PanelAssignmentStatus;
use Illuminate\Database\Seeder;

class PanelAssignmentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'ASSIGNED', 'name' => 'Assigned'],
            ['code' => 'ACTIVE', 'name' => 'Active'],
            ['code' => 'REPLACED', 'name' => 'Replaced'],
            ['code' => 'WITHDRAWN', 'name' => 'Withdrawn'],
            ['code' => 'COMPLETED', 'name' => 'Completed'],
        ];

        foreach ($statuses as $status) {
            PanelAssignmentStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
