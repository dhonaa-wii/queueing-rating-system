<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['code' => 'SUPER_ADMIN', 'name' => 'Super Administrator'],
            ['code' => 'ADMIN', 'name' => 'Administrator'],
            ['code' => 'PANELIST', 'name' => 'Panelist'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['code' => $role['code']], $role);
        }
    }
}
