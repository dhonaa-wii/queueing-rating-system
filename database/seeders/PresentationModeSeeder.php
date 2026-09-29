<?php

namespace Database\Seeders;

use App\Models\PresentationMode;
use Illuminate\Database\Seeder;

class PresentationModeSeeder extends Seeder
{
    public function run(): void
    {
        $modes = [
            ['code' => 'STANDARD', 'name' => 'Standard'],
            ['code' => 'TITLE_PROPOSAL', 'name' => 'Title Proposal'],
        ];

        foreach ($modes as $mode) {
            PresentationMode::updateOrCreate(['code' => $mode['code']], $mode);
        }
    }
}
