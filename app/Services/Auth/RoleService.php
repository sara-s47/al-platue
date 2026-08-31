<?php

namespace App\Services\Auth;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function listRoles(): Collection
    {
        return Role::query()->with('permissions')->get();
    }

    public function listPermissions(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }

    public function createRole(string $name, array $permissions = []): Role
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        if ($permissions) {
            $role->syncPermissions($permissions);
        }

        return $role->load('permissions');
    }

    public function updateRole(int $id, array $data, ?array $permissions = null): Role
    {
        $role = Role::findOrFail($id);
        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }
        if ($permissions !== null) {
            $role->syncPermissions($permissions);
        }

        return $role->fresh('permissions');
    }

    public function deleteRole(int $id): bool
    {
        return (bool) Role::findOrFail($id)->delete();
    }
}