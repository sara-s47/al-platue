<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderCategoriesRequest;
use App\Http\Requests\Api\V1\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    public function __construct(protected CategoryService $categoryService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->categoryService->list((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($c) => new CategoryResource($c)));
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($this->categoryService->create($request->validated())), null, 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($this->categoryService->find($id)));
    }

    public function update(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($this->categoryService->update($id, $request->validated())));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->categoryService->delete($id);
        return ApiResponse::success(message: 'Category deleted.');
    }

    public function reorder(ReorderCategoriesRequest $request): JsonResponse
    {
        $this->categoryService->reorder($request->ordered_ids);
        return ApiResponse::success(message: 'Categories reordered.');
    }

    public function toggle(int $id): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($this->categoryService->toggleActive($id)));
    }
}