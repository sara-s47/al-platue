<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EquipmentResource;
use App\Http\Responses\ApiResponse;
use App\Services\Equipment\EquipmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function __construct(protected EquipmentService $equipmentService)
    {
    }

    public function index(Request $request, int $studioId): JsonResponse
    {
        $request->validate([
            'start_at' => 'nullable|required_with:end_at|date',
            'end_at' => 'nullable|required_with:start_at|date|after:start_at',
        ]);

        $items = $this->equipmentService->getForStudio(
            $studioId,
            $request->filled('start_at') ? Carbon::parse($request->start_at) : null,
            $request->filled('end_at') ? Carbon::parse($request->end_at) : null,
        );

        return ApiResponse::success(EquipmentResource::collection(collect($items)));
    }
}
