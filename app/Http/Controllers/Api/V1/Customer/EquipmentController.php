<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EquipmentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Equipment\EquipmentService;
use Illuminate\Http\JsonResponse;

class EquipmentController extends Controller
{
    public function __construct(protected EquipmentService $equipmentService)
    {
    }

    public function index(int $studioId): JsonResponse
    {
        $items = $this->equipmentService->getForStudio($studioId);

        return ApiResponse::success(EquipmentResource::collection(collect($items)));
    }
}