<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreHospitalityCategoryRequest;
use App\Http\Requests\Api\V1\Admin\StoreHospitalityItemRequest;
use App\Http\Resources\Api\V1\HospitalityItemResource;
use App\Http\Responses\ApiResponse;
use App\Services\Hospitality\HospitalityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHospitalityController extends Controller
{
    public function __construct(protected HospitalityService $hospitalityService) {}

    public function categories(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->hospitalityService->paginateCategories((int) $request->get('per_page', 15)));
    }

    public function storeCategory(StoreHospitalityCategoryRequest $request): JsonResponse
    {
        return ApiResponse::success($this->hospitalityService->createCategory($request->validated()), null, 201);
    }

    public function items(Request $request): JsonResponse
    {
        $p = $this->hospitalityService->paginateItems((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($i) => new HospitalityItemResource($i)));
    }

    public function storeItem(StoreHospitalityItemRequest $request): JsonResponse
    {
        return ApiResponse::success(new HospitalityItemResource($this->hospitalityService->createItem($request->validated())), null, 201);
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(new HospitalityItemResource($this->hospitalityService->updateItem($id, $request->all())));
    }

    public function destroyItem(int $id): JsonResponse
    {
        $this->hospitalityService->deleteItem($id);
        return ApiResponse::success(message: 'Item deleted.');
    }

    public function assign(int $itemId, int $studioId): JsonResponse
    {
        $this->hospitalityService->assignToStudio($itemId, $studioId);
        return ApiResponse::success(message: 'Item assigned.');
    }

    public function unassign(int $itemId, int $studioId): JsonResponse
    {
        $this->hospitalityService->unassignFromStudio($itemId, $studioId);
        return ApiResponse::success(message: 'Item unassigned.');
    }
}