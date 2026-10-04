<?php

namespace Database\Seeders;

use App\Models\QueueStrategy;
use Illuminate\Database\Seeder;

class QueueStrategySeeder extends Seeder
{
    public function run(): void
    {
        $strategies = [
            ['code' => 'FIFO', 'name' => 'First In, First Out', 'is_active' => true],
            ['code' => 'SECTION_BASED', 'name' => 'Section Based', 'is_active' => true],
            ['code' => 'RANDOM_DRAW', 'name' => 'Random Draw', 'is_active' => true],
        ];

        foreach ($strategies as $strategy) {
            QueueStrategy::updateOrCreate(['code' => $strategy['code']], $strategy);
        }
    }
}
