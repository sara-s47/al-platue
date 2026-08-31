<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\HospitalityItemResource;
use App\Http\Responses\ApiResponse;
use App\Services\Hospitality\HospitalityService;
use Illuminate\Http\JsonResponse;

class HospitalityController extends Controller
{
    public function __construct(protected HospitalityService $hospitalityService)
    {
    }

    public function index(int $studioId): JsonResponse
    {
        $items = $this->hospitalityService->getForStudio($studioId);

        return ApiResponse::success(HospitalityItemResource::collection(collect($items)));
    }
}