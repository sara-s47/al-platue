<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudioItemResource;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\StudioItemService;
use Illuminate\Http\JsonResponse;

class StudioItemController extends Controller
{
    public function __construct(protected StudioItemService $studioItemService)
    {
    }

    public function index(int $studioId): JsonResponse
    {
        $items = $this->studioItemService->listForStudio($studioId, activeOnly: true);

        return ApiResponse::success(StudioItemResource::collection($items));
    }
}
