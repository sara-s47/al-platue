<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreRoleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateRoleRequest;
use App\Http\Resources\Api\V1\PermissionResource;
use App\Http\Resources\Api\V1\RoleResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\RoleService;
use Illuminate\Http\JsonResponse;

class AdminRoleController extends Controller
{
    public function __construct(protected RoleService $roleService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(RoleResource::collection($this->roleService->listRoles()));
    }

    public function permissions(): JsonResponse
    {
        return ApiResponse::success(
            PermissionResource::collection($this->roleService->listPermissions())
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $permissionIds = $data['permission_ids'] ?? $data['permissions'] ?? [];

        return ApiResponse::success(
            new RoleResource($this->roleService->createRole($data['name'], $permissionIds)),
            null,
            201,
        );
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $permissionIds = array_key_exists('permission_ids', $data)
            ? $data['permission_ids']
            : (array_key_exists('permissions', $data) ? $data['permissions'] : null);

        unset($data['permission_ids'], $data['permissions']);

        return ApiResponse::success(
            new RoleResource($this->roleService->updateRole($id, $data, $permissionIds))
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->roleService->deleteRole($id);

        return ApiResponse::success(message: 'Role deleted.');
    }
}
