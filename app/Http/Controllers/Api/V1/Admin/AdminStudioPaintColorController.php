<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudioPaintColorResource;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\StudioPaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStudioPaintColorController extends Controller
{
    public function __construct(protected StudioPaintService $paintService) {}

    public function index(int $studioId): JsonResponse
    {
        return ApiResponse::success(
            StudioPaintColorResource::collection($this->paintService->listForStudio($studioId)),
        );
    }

    public function store(Request $request, int $studioId): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'hex' => 'nullable|string|max:20',
            'is_default' => 'sometimes|boolean',
            'price' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $color = $this->paintService->createColor($studioId, $data);

        return ApiResponse::success(new StudioPaintColorResource($color), null, 201);
    }

    public function update(Request $request, int $studioId, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'hex' => 'nullable|string|max:20',
            'is_default' => 'sometimes|boolean',
            'price' => 'nullable|numeric|min:0',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $color = $this->paintService->updateColor($studioId, $id, $data);

        return ApiResponse::success(new StudioPaintColorResource($color));
    }

    public function destroy(int $studioId, int $id): JsonResponse
    {
        $this->paintService->deleteColor($studioId, $id);

        return ApiResponse::success(message: 'Paint color deleted.');
    }
}
