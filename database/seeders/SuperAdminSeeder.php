<?php

namespace Database\Seeders;

use App\Models\AccountStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;

/**
 * Bootstrap account only: creates the first Super Administrator so the
 * User Management module (Super Admin-only, not yet built) has someone
 * able to log in and create real Admin/Panelist accounts. Not part of
 * the documented seed data in database-schema.md.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $temporaryStatus = AccountStatus::where('code', 'TEMPORARY_CREDENTIALS_ISSUED')->firstOrFail();
        $superAdminRole = Role::where('code', 'SUPER_ADMIN')->firstOrFail();

        $user = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'email' => null,
                'password' => 'ChangeMe123!',
                'account_status_id' => $temporaryStatus->id,
                'must_change_password' => true,
            ]
        );

        UserRole::firstOrCreate([
            'user_id' => $user->id,
            'role_id' => $superAdminRole->id,
        ]);

        $user->profile()->firstOrCreate([], [
            'first_name' => 'System',
            'last_name' => 'Administrator',
        ]);
    }
}
