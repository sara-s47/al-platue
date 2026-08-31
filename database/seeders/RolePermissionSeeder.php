<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    protected array $roles = [
        'super_admin',
        'admin',
        'staff',
        'customer',
    ];

    /**
     * @var list<string>
     */
    protected array $permissions = [
        'studios.manage',
        'categories.manage',
        'schedules.manage',
        'bookings.manage',
        'equipment.manage',
        'hospitality.manage',
        'packages.manage',
        'pricing.manage',
        'cancellation-policies.manage',
        'reschedule-policies.manage',
        'payments.manage',
        'refunds.manage',
        'loyalty.manage',
        'campaigns.manage',
        'promo-codes.manage',
        'segments.manage',
        'notifications.manage',
        'reviews.manage',
        'content.manage',
        'users.manage',
        'roles.manage',
        'settings.manage',
        'audit.view',
        'reports.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach ($this->roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        $superAdmin = Role::findByName('super_admin', 'web');
        $superAdmin->syncPermissions($this->permissions);
    }
}
