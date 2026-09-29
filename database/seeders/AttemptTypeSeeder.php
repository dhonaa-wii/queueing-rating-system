<?php

namespace Database\Seeders;

use App\Models\AttemptType;
use Illuminate\Database\Seeder;

class AttemptTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'INITIAL', 'name' => 'Initial', 'is_active' => true],
            ['code' => 'RE_DEFENSE', 'name' => 'Re-Defense', 'is_active' => true],
            ['code' => 'TITLE_REVISION', 'name' => 'Title Revision', 'is_active' => true],
            ['code' => 'PROJECT_REVISION', 'name' => 'Project Revision', 'is_active' => true],
            ['code' => 'PROJECT_CHANGE', 'name' => 'Project Change', 'is_active' => true],
            ['code' => 'OTHER_AUTHORIZED', 'name' => 'Other Authorized', 'is_active' => true],
        ];

        foreach ($types as $type) {
            AttemptType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
