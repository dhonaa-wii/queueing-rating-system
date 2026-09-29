<?php

namespace Database\Seeders;

use App\Models\AccountStatus;
use Illuminate\Database\Seeder;

class AccountStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'TEMPORARY_CREDENTIALS_ISSUED', 'name' => 'Temporary Credentials Issued', 'is_login_allowed' => true],
            ['code' => 'ACTIVE', 'name' => 'Active', 'is_login_allowed' => true],
            ['code' => 'INACTIVE', 'name' => 'Inactive', 'is_login_allowed' => false],
            ['code' => 'PASSWORD_RESET_REQUIRED', 'name' => 'Password Reset Required', 'is_login_allowed' => true],
            ['code' => 'LOCKED', 'name' => 'Locked', 'is_login_allowed' => false],
        ];

        foreach ($statuses as $status) {
            AccountStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
