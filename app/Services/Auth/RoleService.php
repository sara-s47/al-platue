<?php

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function listRoles(): Collection
    {
        return Role::query()->with('permissions')->orderBy('name')->get();
    }

    public function listPermissions(): Collection
    {
        return Permission::query()->orderBy('name')->get(['id', 'name', 'guard_name']);
    }

    public function createRole(string $name, array $permissionIds = []): Role
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);

        if ($permissionIds !== []) {
            $role->syncPermissions($this->resolvePermissions($permissionIds));
        }

        return $role->load('permissions');
    }

    public function updateRole(int $id, array $data, ?array $permissionIds = null): Role
    {
        $role = Role::findOrFail($id);

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if ($permissionIds !== null) {
            $role->syncPermissions($this->resolvePermissions($permissionIds));
        }

        return $role->fresh('permissions');
    }

    public function deleteRole(int $id): bool
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, ['super_admin', 'customer'], true)) {
            throw new BusinessException('This role cannot be deleted.', 'role_protected');
        }

        return (bool) $role->delete();
    }

    /**
     * Accept permission IDs (preferred) or legacy permission name strings.
     *
     * @param  array<int, int|string>  $permissions
     * @return Collection<int, Permission>
     */
    public function resolvePermissions(array $permissions): Collection
    {
        if ($permissions === []) {
            return collect();
        }

        $ids = [];
        $names = [];

        foreach ($permissions as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            } else {
                $names[] = (string) $value;
            }
        }

        $resolved = collect();

        if ($ids !== []) {
            $byId = Permission::query()->whereIn('id', $ids)->get();
            if ($byId->count() !== count(array_unique($ids))) {
                throw new BusinessException('One or more permission IDs are invalid.', 'permission_invalid');
            }
            $resolved = $resolved->merge($byId);
        }

        if ($names !== []) {
            $byName = Permission::query()->whereIn('name', $names)->get();
            if ($byName->count() !== count(array_unique($names))) {
                throw new BusinessException('One or more permission names are invalid.', 'permission_invalid');
            }
            $resolved = $resolved->merge($byName);
        }

        return $resolved->unique('id')->values();
    }
}
