<?php

namespace Database\Seeders;

use App\Models\RequirementStatus;
use Illuminate\Database\Seeder;

class RequirementStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'PENDING', 'name' => 'Pending'],
            ['code' => 'IN_PROGRESS', 'name' => 'In Progress'],
            ['code' => 'COMPLETED', 'name' => 'Completed'],
            ['code' => 'WAIVED', 'name' => 'Waived'],
        ];

        foreach ($statuses as $status) {
            RequirementStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
