<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAdminStaffRequest;
use App\Http\Requests\Api\V1\Admin\SyncAdminPermissionsRequest;
use App\Http\Requests\Api\V1\Admin\SyncAdminRolesRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminStaffRequest;
use App\Http\Resources\Api\V1\AdminStaffResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AdminStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStaffController extends Controller
{
    public function __construct(protected AdminStaffService $adminStaffService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->adminStaffService->paginate((int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($user) => new AdminStaffResource($user)));
    }

    public function store(StoreAdminStaffRequest $request): JsonResponse
    {
        $admin = $this->adminStaffService->create($request->validated());

        return ApiResponse::success(new AdminStaffResource($admin), 'Admin created.', 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new AdminStaffResource($this->adminStaffService->findOrFail($id)));
    }

    public function update(UpdateAdminStaffRequest $request, int $id): JsonResponse
    {
        $admin = $this->adminStaffService->update($id, $request->validated());

        return ApiResponse::success(new AdminStaffResource($admin));
    }

    public function syncRoles(SyncAdminRolesRequest $request, int $id): JsonResponse
    {
        $admin = $this->adminStaffService->findOrFail($id);
        $admin = $this->adminStaffService->syncRoles($admin, $request->validated('role_ids'));

        return ApiResponse::success(new AdminStaffResource($admin), 'Roles updated.');
    }

    public function syncPermissions(SyncAdminPermissionsRequest $request, int $id): JsonResponse
    {
        $admin = $this->adminStaffService->findOrFail($id);
        $admin = $this->adminStaffService->syncDirectPermissions($admin, $request->validated('permission_ids'));

        return ApiResponse::success(new AdminStaffResource($admin), 'Permissions updated.');
    }

    public function givePermissions(SyncAdminPermissionsRequest $request, int $id): JsonResponse
    {
        $admin = $this->adminStaffService->findOrFail($id);
        $admin = $this->adminStaffService->givePermissions($admin, $request->validated('permission_ids'));

        return ApiResponse::success(new AdminStaffResource($admin), 'Permissions granted.');
    }

    public function revokePermissions(SyncAdminPermissionsRequest $request, int $id): JsonResponse
    {
        $admin = $this->adminStaffService->findOrFail($id);
        $admin = $this->adminStaffService->revokePermissions($admin, $request->validated('permission_ids'));

        return ApiResponse::success(new AdminStaffResource($admin), 'Permissions revoked.');
    }
}
