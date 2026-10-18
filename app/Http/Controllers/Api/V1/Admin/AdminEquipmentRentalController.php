<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EquipmentRentalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateEquipmentRentalStatusRequest;
use App\Http\Resources\Api\V1\EquipmentRentalResource;
use App\Http\Responses\ApiResponse;
use App\Services\Equipment\EquipmentRentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEquipmentRentalController extends Controller
{
    public function __construct(protected EquipmentRentalService $rentalService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => 'nullable|string|max:20',
            'payment_method' => 'nullable|in:online,cash',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);

        $p = $this->rentalService->paginateForAdmin($filters, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($p->through(fn ($r) => new EquipmentRentalResource($r)));
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new EquipmentRentalResource($this->rentalService->findForAdmin($id)));
    }

    public function updateStatus(UpdateEquipmentRentalStatusRequest $request, int $id): JsonResponse
    {
        $rental = $this->rentalService->updateStatus($id, EquipmentRentalStatus::from($request->status));

        return ApiResponse::success(new EquipmentRentalResource($rental), 'Rental status updated.');
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        $rental = $this->rentalService->markCashPaid($id, isset($data['amount']) ? (float) $data['amount'] : null);

        return ApiResponse::success(new EquipmentRentalResource($rental), 'Cash payment recorded.');
    }
}
