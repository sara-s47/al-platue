<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CatalogSectionCoverResource;
use App\Http\Responses\ApiResponse;
use App\Services\Content\CatalogSectionCoverService;
use Illuminate\Http\JsonResponse;

class CatalogSectionCoverController extends Controller
{
    public function __construct(protected CatalogSectionCoverService $coverService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            CatalogSectionCoverResource::collection($this->coverService->all()),
        );
    }
}
