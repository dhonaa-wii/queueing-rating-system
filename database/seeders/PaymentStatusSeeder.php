<?php

namespace Database\Seeders;

use App\Models\PaymentStatus;
use Illuminate\Database\Seeder;

class PaymentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'NOT_REQUIRED', 'name' => 'Not Required', 'is_terminal' => true],
            ['code' => 'PENDING_VERIFICATION', 'name' => 'Pending Verification', 'is_terminal' => false],
            ['code' => 'VERIFIED', 'name' => 'Verified', 'is_terminal' => true],
            ['code' => 'VERIFICATION_CONCERN', 'name' => 'Verification Concern', 'is_terminal' => false],
            ['code' => 'REFERRED_TO_ADMIN', 'name' => 'Referred to Admin', 'is_terminal' => false],
            ['code' => 'RESOLVED', 'name' => 'Resolved', 'is_terminal' => true],
            ['code' => 'NOT_VERIFIED', 'name' => 'Not Verified', 'is_terminal' => true],
        ];

        foreach ($statuses as $status) {
            PaymentStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
