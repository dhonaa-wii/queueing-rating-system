<?php

namespace Database\Seeders;

use App\Models\PresentationActionType;
use Illuminate\Database\Seeder;

class PresentationActionTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'CALL', 'name' => 'Call'],
            ['code' => 'START', 'name' => 'Start'],
            ['code' => 'PAUSE', 'name' => 'Pause'],
            ['code' => 'RESUME', 'name' => 'Resume'],
            ['code' => 'COMPLETE', 'name' => 'Complete'],
            ['code' => 'DEFER', 'name' => 'Defer'],
            ['code' => 'REFER_PAYMENT_TO_ADMIN', 'name' => 'Refer Payment to Admin'],
            ['code' => 'END_ROOM', 'name' => 'End Room'],
            ['code' => 'EMERGENCY_OVERRIDE', 'name' => 'Emergency Override'],
        ];

        foreach ($types as $type) {
            PresentationActionType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
