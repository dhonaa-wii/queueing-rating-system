<?php

namespace Database\Seeders;

use App\Models\DecisionStatus;
use Illuminate\Database\Seeder;

class DecisionStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'DRAFT', 'name' => 'Draft'],
            ['code' => 'PENDING_CONFIRMATION', 'name' => 'Pending Confirmation'],
            ['code' => 'FINALIZED', 'name' => 'Finalized'],
            ['code' => 'REOPENED', 'name' => 'Reopened'],
        ];

        foreach ($statuses as $status) {
            DecisionStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
