<?php

namespace Database\Seeders;

use App\Models\ConnectionStatus;
use Illuminate\Database\Seeder;

class ConnectionStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'CONNECTED', 'name' => 'Connected'],
            ['code' => 'DISCONNECTED', 'name' => 'Disconnected'],
            ['code' => 'REVOKED', 'name' => 'Revoked'],
            ['code' => 'EXPIRED', 'name' => 'Expired'],
        ];

        foreach ($statuses as $status) {
            ConnectionStatus::updateOrCreate(['code' => $status['code']], $status);
        }
    }
}
