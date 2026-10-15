<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderStudiosRequest;
use App\Http\Requests\Api\V1\Admin\StoreStudioRequest;
use App\Http\Requests\Api\V1\Admin\UpdateStudioRequest;
use App\Http\Requests\Api\V1\Admin\UploadStudioImagesRequest;
use App\Http\Resources\Api\V1\StudioResource;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\StudioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

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
        $data = $request->safe()->except(['image', 'images']);
        $paths = $this->storeUploadedImages($request);

        return ApiResponse::success(new StudioResource($this->studioService->create($data, $paths)), null, 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new StudioResource($this->studioService->find($id)->load('images')));
    }

    public function update(UpdateStudioRequest $request, int $id): JsonResponse
    {
        $data = $request->safe()->except(['image', 'images', 'clear_images']);
        $paths = null;

        if ($request->boolean('clear_images')) {
            $paths = [];
        } elseif ($request->hasFile('image') || $request->hasFile('images')) {
            $paths = $this->storeUploadedImages($request);
        }

        return ApiResponse::success(new StudioResource($this->studioService->update($id, $data, $paths)));
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

    public function reorder(ReorderStudiosRequest $request): JsonResponse
    {
        $studios = $this->studioService->reorder($request->validated('ordered_ids'));

        return ApiResponse::success(StudioResource::collection($studios), 'Studios reordered.');
    }

    public function uploadImages(UploadStudioImagesRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new StudioResource($this->studioService->uploadImages($id, $request->images)));
    }

    /**
     * @return list<string>
     */
    protected function storeUploadedImages(Request $request): array
    {
        $files = [];

        if ($request->hasFile('image')) {
            $files[] = $request->file('image');
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $files[] = $file;
            }
        }

        return array_map(
            fn (UploadedFile $file) => $file->store('studios', 'public'),
            $files,
        );
    }
}