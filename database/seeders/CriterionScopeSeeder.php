<?php

namespace Database\Seeders;

use App\Models\CriterionScope;
use Illuminate\Database\Seeder;

class CriterionScopeSeeder extends Seeder
{
    public function run(): void
    {
        $scopes = [
            ['code' => 'GROUP', 'name' => 'Group'],
            ['code' => 'INDIVIDUAL_STUDENT', 'name' => 'Individual Student'],
        ];

        foreach ($scopes as $scope) {
            CriterionScope::updateOrCreate(['code' => $scope['code']], $scope);
        }
    }
}
