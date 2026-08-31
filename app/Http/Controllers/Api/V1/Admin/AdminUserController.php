<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->userService->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($u) => new UserResource($u)));
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new UserResource($this->userService->findOrFail($id)));
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new UserResource($this->userService->update($id, $request->validated())));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate(['status' => 'required|string']);
        return ApiResponse::success(new UserResource($this->userService->updateStatus($id, $request->status)));
    }
}