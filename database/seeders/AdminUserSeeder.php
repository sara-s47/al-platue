<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::updateOrCreate(
            ['phone' => '+201000000001'],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@el-platue.test',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
        $superAdmin->syncRoles(Role::findByName('super_admin', 'web'));

        $admin = User::updateOrCreate(
            ['phone' => '+201000000002'],
            [
                'name' => 'Admin User',
                'email' => 'admin@el-platue.test',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
        $admin->syncRoles(Role::findByName('admin', 'web'));

        $customer = User::updateOrCreate(
            ['phone' => '+201000000003'],
            [
                'name' => 'Demo Customer',
                'email' => 'customer@el-platue.test',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
        $customer->syncRoles(Role::findByName('customer', 'web'));
    }
}
