<?php

namespace Database\Seeders;

use App\Models\CategoryStatus;
use Illuminate\Database\Seeder;

class CategoryStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'DRAFT', 'name' => 'Draft', 'allows_registration' => false, 'allows_queue_generation' => false, 'is_terminal' => false],
            ['code' => 'REGISTRATION_OPEN', 'name' => 'Registration Open', 'allows_registration' => true, 'allows_queue_generation' => false, 'is_terminal' => false],
            ['code' => 'SETUP_INCOMPLETE', 'name' => 'Setup Incomplete', 'allows_registration' => true, 'allows_queue_generation' => false, 'is_terminal' => false],
            ['code' => 'UPCOMING', 'name' => 'Upcoming', 'allows_registration' => false, 'allows_queue_generation' => false, 'is_terminal' => false],
            ['code' => 'ACTIVE', 'name' => 'Active', 'allows_registration' => false, 'allows_queue_generation' => false, 'is_terminal' => false],
            ['code' => 'COMPLETED', 'name' => 'Completed', 'allows_registration' => false, 'allows_queue_generation' => false, 'is_terminal' => true],
            ['code' => 'ARCHIVED', 'name' => 'Archived', 'allows_registration' => false, 'allows_queue_generation' => false, 'is_terminal' => true],
        ];

        foreach ($statuses as $status) {
            CategoryStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
