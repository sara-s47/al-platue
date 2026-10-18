<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreStudioItemRequest;
use App\Http\Requests\Api\V1\Admin\UpdateStudioItemRequest;
use App\Http\Resources\Api\V1\StudioItemResource;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\StudioItemService;
use Illuminate\Http\JsonResponse;

class AdminStudioItemController extends Controller
{
    public function __construct(protected StudioItemService $studioItemService)
    {
    }

    public function index(int $studioId): JsonResponse
    {
        $items = $this->studioItemService->listForStudio($studioId);

        return ApiResponse::success(StudioItemResource::collection($items));
    }

    public function store(StoreStudioItemRequest $request, int $studioId): JsonResponse
    {
        $path = $request->hasFile('image')
            ? $request->file('image')->store('studio-items', 'public')
            : null;

        $item = $this->studioItemService->create(
            $studioId,
            $request->safe()->except(['image']),
            $path,
        );

        return ApiResponse::success(new StudioItemResource($item), null, 201);
    }

    public function show(int $studioId, int $id): JsonResponse
    {
        return ApiResponse::success(new StudioItemResource(
            $this->studioItemService->findForStudio($studioId, $id),
        ));
    }

    public function update(UpdateStudioItemRequest $request, int $studioId, int $id): JsonResponse
    {
        $path = $request->hasFile('image')
            ? $request->file('image')->store('studio-items', 'public')
            : null;

        $item = $this->studioItemService->update(
            $studioId,
            $id,
            $request->safe()->except(['image', 'clear_image']),
            $path,
            $request->boolean('clear_image'),
        );

        return ApiResponse::success(new StudioItemResource($item));
    }

    public function destroy(int $studioId, int $id): JsonResponse
    {
        $this->studioItemService->delete($studioId, $id);

        return ApiResponse::success(message: 'Studio item deleted.');
    }
}
