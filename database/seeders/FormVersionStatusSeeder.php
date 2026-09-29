<?php

namespace Database\Seeders;

use App\Models\FormVersionStatus;
use Illuminate\Database\Seeder;

class FormVersionStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'DRAFT', 'name' => 'Draft'],
            ['code' => 'ACTIVE', 'name' => 'Active'],
            ['code' => 'RETIRED', 'name' => 'Retired'],
        ];

        foreach ($statuses as $status) {
            FormVersionStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
