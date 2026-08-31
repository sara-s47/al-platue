<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PackageResource;
use App\Http\Responses\ApiResponse;
use App\Services\Inventory\PackageService;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    public function __construct(protected PackageService $packageService)
    {
    }

    public function index(int $studioId): JsonResponse
    {
        return ApiResponse::success(PackageResource::collection($this->packageService->getForStudio($studioId)));
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new PackageResource($this->packageService->findOrFail($id)));
    }
}