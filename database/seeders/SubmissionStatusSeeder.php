<?php

namespace Database\Seeders;

use App\Models\SubmissionStatus;
use Illuminate\Database\Seeder;

class SubmissionStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'DRAFT', 'name' => 'Draft'],
            ['code' => 'SUBMITTED', 'name' => 'Submitted'],
            ['code' => 'REOPENED', 'name' => 'Reopened'],
            ['code' => 'FINALIZED', 'name' => 'Finalized'],
            ['code' => 'INVALIDATED', 'name' => 'Invalidated'],
        ];

        foreach ($statuses as $status) {
            SubmissionStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
