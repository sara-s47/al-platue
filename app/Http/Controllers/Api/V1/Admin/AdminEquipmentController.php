<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AssignEquipmentRequest;
use App\Http\Requests\Api\V1\Admin\StoreEquipmentRequest;
use App\Http\Requests\Api\V1\Admin\UpdateEquipmentRequest;
use App\Http\Resources\Api\V1\EquipmentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Equipment\EquipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEquipmentController extends Controller
{
    public function __construct(protected EquipmentService $equipmentService) {}

    public function index(Request $request): JsonResponse
    {
        $p = $this->equipmentService->paginate((int) $request->get('per_page', 15));
        return ApiResponse::paginated($p->through(fn ($e) => new EquipmentResource($e)));
    }

    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        return ApiResponse::success(new EquipmentResource($this->equipmentService->create($request->validated())), null, 201);
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new EquipmentResource($this->equipmentService->findOrFail($id)));
    }

    public function update(UpdateEquipmentRequest $request, int $id): JsonResponse
    {
        return ApiResponse::success(new EquipmentResource($this->equipmentService->update($id, $request->validated())));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->equipmentService->delete($id);
        return ApiResponse::success(message: 'Equipment deleted.');
    }

    public function assign(AssignEquipmentRequest $request, int $equipmentId): JsonResponse
    {
        $this->equipmentService->assignToStudio($equipmentId, (int) $request->studio_id, (int) $request->quantity);
        return ApiResponse::success(message: 'Equipment assigned.');
    }

    public function unassign(int $equipmentId, int $studioId): JsonResponse
    {
        $this->equipmentService->unassignFromStudio($equipmentId, $studioId);
        return ApiResponse::success(message: 'Equipment unassigned.');
    }
}