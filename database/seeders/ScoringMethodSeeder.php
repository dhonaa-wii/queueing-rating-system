<?php

namespace Database\Seeders;

use App\Models\ScoringMethod;
use Illuminate\Database\Seeder;

class ScoringMethodSeeder extends Seeder
{
    /**
     * TENTATIVE placeholder value — schema doc says "keep flexible until
     * final department computation rule is confirmed." Re-seed with
     * confirmed values before final defense/deployment.
     */
    public function run(): void
    {
        $methods = [
            ['code' => 'WEIGHTED_AVERAGE', 'name' => 'Weighted Average', 'description' => 'Placeholder scoring method pending department confirmation.', 'is_active' => true],
        ];

        foreach ($methods as $method) {
            ScoringMethod::updateOrCreate(['code' => $method['code']], $method);
        }
    }
}
