<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePackageRequest;
use App\Http\Resources\Api\V1\PackageResource;
use App\Http\Responses\ApiResponse;
use App\Services\Inventory\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPackageController extends Controller
{
    public function __construct(protected PackageService $packageService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->packageService->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($pk) => new PackageResource($pk)));
    }

    public function store(StorePackageRequest $request): JsonResponse
    {
        $d = $request->validated();
        $pkg = $this->packageService->create($d, $d['equipment'] ?? [], $d['hospitality'] ?? []);
        return ApiResponse::success(new PackageResource($pkg), null, 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new PackageResource($this->packageService->findOrFail($id)));
    }

    public function update(StorePackageRequest $request, int $id): JsonResponse
    {
        $d = $request->validated();
        $pkg = $this->packageService->update($id, $d, $d['equipment'] ?? null, $d['hospitality'] ?? null);
        return ApiResponse::success(new PackageResource($pkg));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->packageService->delete($id);
        return ApiResponse::success(message: 'Package deleted.');
    }
}