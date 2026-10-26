<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EquipmentCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Equipment\EquipmentCategoryService;
use Illuminate\Http\JsonResponse;

class EquipmentCategoryController extends Controller
{
    public function __construct(protected EquipmentCategoryService $categoryService)
    {
    }

    public function index(): JsonResponse
    {
        $categories = $this->categoryService->all()->where('is_active', true)->values();

        return ApiResponse::success(EquipmentCategoryResource::collection($categories));
    }
}
