<?php

namespace Database\Seeders;

use App\Models\SubstitutionStatus;
use Illuminate\Database\Seeder;

class SubstitutionStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'PENDING', 'name' => 'Pending'],
            ['code' => 'APPROVED', 'name' => 'Approved'],
            ['code' => 'REJECTED', 'name' => 'Rejected'],
            ['code' => 'CANCELLED', 'name' => 'Cancelled'],
        ];

        foreach ($statuses as $status) {
            SubstitutionStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
