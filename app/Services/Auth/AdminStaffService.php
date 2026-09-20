<?php

namespace App\Services\Auth;

use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class AdminStaffService
{
    /** @var list<string> */
    public const STAFF_ROLES = ['super_admin', 'admin', 'staff'];

    public function __construct(
        protected RoleService $roleService,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->role(self::STAFF_ROLES)
            ->with(['roles.permissions', 'permissions'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findOrFail(int $id): User
    {
        $user = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->findOrFail($id);

        if (! $user->hasAnyRole(self::STAFF_ROLES)) {
            throw new BusinessException('User is not an admin/staff account.', 'not_admin_user', 404);
        }

        return $user;
    }

    /**
     * @param  array{name: string, email?: string|null, phone: string, password: string, status?: string, role_ids?: array<int, int>, permission_ids?: array<int, int>}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'password' => $data['password'],
                'status' => $data['status'] ?? 'active',
            ]);

            $roleIds = $data['role_ids'] ?? [];
            if ($roleIds === []) {
                $default = Role::findByName('admin', 'web');
                $roleIds = [$default->id];
            }

            $this->syncRoles($user, $roleIds);

            if (array_key_exists('permission_ids', $data)) {
                $this->syncDirectPermissions($user, $data['permission_ids'] ?? []);
            }

            return $user->fresh(['roles.permissions', 'permissions']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): User
    {
        $user = $this->findOrFail($id);

        return DB::transaction(function () use ($user, $data) {
            $payload = collect($data)->only(['name', 'email', 'phone', 'status', 'password'])->filter(fn ($v) => $v !== null)->all();

            if ($payload !== []) {
                $user->update($payload);
            }

            if (array_key_exists('role_ids', $data)) {
                $this->syncRoles($user, $data['role_ids'] ?? []);
            }

            if (array_key_exists('permission_ids', $data)) {
                $this->syncDirectPermissions($user, $data['permission_ids'] ?? []);
            }

            return $user->fresh(['roles.permissions', 'permissions']);
        });
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function syncRoles(User $user, array $roleIds): User
    {
        $roles = Role::query()->whereIn('id', $roleIds)->get();

        if ($roles->count() !== count(array_unique($roleIds))) {
            throw new BusinessException('One or more role IDs are invalid.', 'role_invalid');
        }

        if ($roles->contains(fn (Role $role) => $role->name === 'customer')) {
            throw new BusinessException('Cannot assign customer role to an admin account.', 'invalid_admin_role');
        }

        if ($roles->isEmpty()) {
            throw new BusinessException('Admin must have at least one staff role.', 'admin_role_required');
        }

        $user->syncRoles($roles);

        return $user->fresh(['roles.permissions', 'permissions']);
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    public function syncDirectPermissions(User $user, array $permissionIds): User
    {
        $permissions = $this->roleService->resolvePermissions($permissionIds);
        $user->syncPermissions($permissions);

        return $user->fresh(['roles.permissions', 'permissions']);
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    public function givePermissions(User $user, array $permissionIds): User
    {
        $permissions = $this->roleService->resolvePermissions($permissionIds);
        $user->givePermissionTo($permissions);

        return $user->fresh(['roles.permissions', 'permissions']);
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    public function revokePermissions(User $user, array $permissionIds): User
    {
        $permissions = $this->roleService->resolvePermissions($permissionIds);
        $user->revokePermissionTo($permissions);

        return $user->fresh(['roles.permissions', 'permissions']);
    }
}
