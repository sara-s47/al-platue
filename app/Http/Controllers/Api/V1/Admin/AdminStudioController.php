<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreStudioRequest;
use App\Http\Requests\Api\V1\Admin\UpdateStudioRequest;
use App\Http\Requests\Api\V1\Admin\UploadStudioImagesRequest;
use App\Http\Resources\Api\V1\StudioResource;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\StudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStudioController extends Controller
{
    public function __construct(protected StudioService $studioService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->studioService->list((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($s) => new StudioResource($s)));
    }

    public function store(StoreStudioRequest $request): JsonResponse
    {
        return ApiResponse::success(new StudioResource($this->studioService->create($request->validated())), null, 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new StudioResource($this->studioService->find($id)->load('images')));
    }

    public function update(UpdateStudioRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new StudioResource($this->studioService->update($id, $request->validated())));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->studioService->delete($id);
        return ApiResponse::success(message: 'Studio deleted.');
    }

    public function toggle(int $id): JsonResponse
    {
        return ApiResponse::success(new StudioResource($this->studioService->toggleActive($id)));
    }

    public function uploadImages(UploadStudioImagesRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new StudioResource($this->studioService->uploadImages($id, $request->images)));
    }
}