<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSegmentRequest;
use App\Http\Resources\Api\V1\SegmentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Promotion\SegmentationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSegmentController extends Controller
{
    public function __construct(protected SegmentationService $segmentationService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->segmentationService->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($s) => new SegmentResource($s)));
    }

    public function store(StoreSegmentRequest $request): JsonResponse
    {
        return ApiResponse::success(new SegmentResource($this->segmentationService->create($request->validated())), null, 201);
    }

    public function update(StoreSegmentRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new SegmentResource($this->segmentationService->update($id, $request->validated())));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->segmentationService->delete($id);
        return ApiResponse::success(message: 'Segment deleted.');
    }

    public function preview(int $id): JsonResponse
    {
        return ApiResponse::success(['count' => $this->segmentationService->previewCount($id)]);
    }
}