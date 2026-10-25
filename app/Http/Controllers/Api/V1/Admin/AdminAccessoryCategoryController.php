<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderAccessoryCategoriesRequest;
use App\Http\Requests\Api\V1\Admin\StoreAccessoryCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAccessoryCategoryRequest;
use App\Http\Resources\Api\V1\AccessoryCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Accessory\AccessoryCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAccessoryCategoryController extends Controller
{
    public function __construct(protected AccessoryCategoryService $categoryService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->categoryService->list((int) $request->get('per_page', 15));

        return ApiResponse::paginated($p->through(fn ($c) => new AccessoryCategoryResource($c)));
    }

    public function store(StoreAccessoryCategoryRequest $request): JsonResponse
    {
        return ApiResponse::success(
            new AccessoryCategoryResource($this->categoryService->create($request->validated())),
            null,
            201,
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new AccessoryCategoryResource($this->categoryService->find($id)));
    }

    public function update(UpdateAccessoryCategoryRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(
            new AccessoryCategoryResource($this->categoryService->update($id, $request->validated())),
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->categoryService->delete($id);

        return ApiResponse::success(message: 'Accessory category deleted.');
    }

    public function reorder(ReorderAccessoryCategoriesRequest $request): JsonResponse
    {
        $this->categoryService->reorder($request->ordered_ids);

        return ApiResponse::success(message: 'Accessory categories reordered.');
    }

    public function toggle(int $id): JsonResponse
    {
        return ApiResponse::success(new AccessoryCategoryResource($this->categoryService->toggleActive($id)));
    }
}
