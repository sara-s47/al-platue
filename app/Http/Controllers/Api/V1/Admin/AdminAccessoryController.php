<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAccessoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAccessoryRequest;
use App\Http\Resources\Api\V1\AccessoryRentalHistoryResource;
use App\Http\Resources\Api\V1\AccessoryResource;
use App\Http\Responses\ApiResponse;
use App\Services\Accessory\AccessoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AdminAccessoryController extends Controller
{
    public function __construct(protected AccessoryService $accessoryService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['category_id', 'search']);
        $p = $this->accessoryService->paginate((int) $request->get('per_page', 15), $filters);

        return ApiResponse::paginated($p->through(fn ($a) => new AccessoryResource($a)));
    }

    public function store(StoreAccessoryRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['image', 'images', 'cover_image']);
        $this->applyCoverUpload($request, $data, 'accessories');
        $paths = $this->storeUploadedImages($request);

        return ApiResponse::success(new AccessoryResource($this->accessoryService->create($data, $paths)), null, 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new AccessoryResource($this->accessoryService->find($id)));
    }

    public function update(UpdateAccessoryRequest $request, int $id): JsonResponse
    {
        $data = $request->safe()->except(['image', 'images', 'clear_images', 'cover_image', 'clear_cover']);
        $this->applyCoverUpload($request, $data, 'accessories');
        $paths = null;

        if ($request->boolean('clear_images')) {
            $paths = [];
        } elseif ($request->hasFile('image') || $request->hasFile('images')) {
            $paths = $this->storeUploadedImages($request);
        }

        return ApiResponse::success(new AccessoryResource($this->accessoryService->update($id, $data, $paths)));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->accessoryService->delete($id);

        return ApiResponse::success(message: 'Accessory deleted.');
    }

    public function rentals(Request $request, int $id): JsonResponse
    {
        $filters = $request->validate([
            'type' => 'nullable|in:standalone,with_studio',
            'status' => 'nullable|string|max:20',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        if (! empty($filters['from'])) {
            $filters['from'] = \Carbon\Carbon::parse($filters['from'])->toDateString();
        }
        if (! empty($filters['to'])) {
            $filters['to'] = \Carbon\Carbon::parse($filters['to'])->toDateString();
        }

        $p = $this->accessoryService->rentalHistory($id, $filters, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($p->through(fn ($item) => new AccessoryRentalHistoryResource($item)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function applyCoverUpload(Request $request, array &$data, string $folder): void
    {
        if ($request->boolean('clear_cover')) {
            $data['cover_image'] = null;

            return;
        }

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store($folder, 'public');
        }
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
            fn (UploadedFile $file) => $file->store('accessories', 'public'),
            $files,
        );
    }
}
