<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreDayCatalogRequest;
use App\Http\Requests\Api\V1\Admin\UpdateDayCatalogRequest;
use App\Http\Resources\Api\V1\DayCatalogOrderHistoryResource;
use App\Http\Resources\Api\V1\DayCatalogProductResource;
use App\Http\Responses\ApiResponse;
use App\Services\DayCatalog\DayCatalogService;
use App\Support\DayCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

abstract class AdminDayCatalogController extends Controller
{
    public function __construct(protected DayCatalogService $catalogService) {}

    abstract protected function catalogType(): string;

    public function index(Request $request): JsonResponse
    {
        $p = $this->catalogService->paginate($this->catalogType(), (int) $request->get('per_page', 15));

        return ApiResponse::paginated($p->through(fn ($item) => new DayCatalogProductResource($item)));
    }

    public function store(StoreDayCatalogRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['image', 'images']);
        $paths = $this->storeUploadedImages($request);

        return ApiResponse::success(
            new DayCatalogProductResource($this->catalogService->create($this->catalogType(), $data, $paths)),
            null,
            201,
        );
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(
            new DayCatalogProductResource($this->catalogService->find($this->catalogType(), $id)),
        );
    }

    public function update(UpdateDayCatalogRequest $request, int $id): JsonResponse
    {
        $data = $request->safe()->except(['image', 'images', 'clear_images']);
        $paths = null;

        if ($request->boolean('clear_images')) {
            $paths = [];
        } elseif ($request->hasFile('image') || $request->hasFile('images')) {
            $paths = $this->storeUploadedImages($request);
        }

        return ApiResponse::success(
            new DayCatalogProductResource($this->catalogService->update($this->catalogType(), $id, $data, $paths)),
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->catalogService->delete($this->catalogType(), $id);

        return ApiResponse::success(message: 'Item deleted.');
    }

    public function orders(Request $request, int $id): JsonResponse
    {
        $filters = $request->validate([
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

        $p = $this->catalogService->orderHistory(
            $this->catalogType(),
            $id,
            $filters,
            (int) $request->get('per_page', 15),
        );

        return ApiResponse::paginated($p->through(fn ($item) => new DayCatalogOrderHistoryResource($item)));
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

        $folder = DayCatalog::storageFolder($this->catalogType());

        return array_map(
            fn (UploadedFile $file) => $file->store($folder, 'public'),
            $files,
        );
    }
}
