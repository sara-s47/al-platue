<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminStaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roles = $this->whenLoaded('roles', function () {
            return $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values();
        });

        $directPermissions = $this->relationLoaded('permissions')
            ? $this->permissions->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
            ])->values()
            : null;

        $allPermissions = $this->relationLoaded('roles') || $this->relationLoaded('permissions')
            ? $this->getAllPermissions()->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
            ])->values()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'roles' => $roles,
            'role_ids' => $this->whenLoaded('roles', fn () => $this->roles->pluck('id')->values()),
            'direct_permissions' => $directPermissions,
            'direct_permission_ids' => $directPermissions?->pluck('id')->values(),
            'permissions' => $allPermissions,
            'permission_ids' => $allPermissions?->pluck('id')->values(),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
