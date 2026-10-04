<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InteriorDesignerResource;
use App\Http\Responses\ApiResponse;
use App\Services\Content\InteriorDesignerService;
use Illuminate\Http\JsonResponse;

class InteriorDesignerController extends Controller
{
    public function __construct(protected InteriorDesignerService $designerService) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(InteriorDesignerResource::collection($this->designerService->listActive()));
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new InteriorDesignerResource($this->designerService->findActive($id)));
    }
}
