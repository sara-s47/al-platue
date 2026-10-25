<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AccessoryCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Accessory\AccessoryCategoryService;
use Illuminate\Http\JsonResponse;

class AccessoryCategoryController extends Controller
{
    public function __construct(protected AccessoryCategoryService $categoryService)
    {
    }

    public function index(): JsonResponse
    {
        $categories = $this->categoryService->all()->where('is_active', true)->values();

        return ApiResponse::success(AccessoryCategoryResource::collection($categories));
    }
}
