<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreRoleRequest;
use App\Http\Requests\Api\V1\Admin\UpdateRoleRequest;
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
        return ApiResponse::success($this->roleService->listPermissions()->pluck('name'));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return ApiResponse::success(new RoleResource($this->roleService->createRole($request->name, $request->permissions ?? [])), null, 201);
    }

    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new RoleResource($this->roleService->updateRole($id, $request->validated(), $request->permissions)));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->roleService->deleteRole($id);
        return ApiResponse::success(message: 'Role deleted.');
    }
}