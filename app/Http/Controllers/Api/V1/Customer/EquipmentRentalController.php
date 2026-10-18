<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CreateEquipmentRentalRequest;
use App\Http\Requests\Api\V1\Customer\EquipmentRentalQuoteRequest;
use App\Http\Resources\Api\V1\EquipmentRentalResource;
use App\Http\Responses\ApiResponse;
use App\Services\Equipment\EquipmentRentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentRentalController extends Controller
{
    public function __construct(protected EquipmentRentalService $rentalService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->rentalService->paginateForUser($request->user()->id, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($r) => new EquipmentRentalResource($r)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(new EquipmentRentalResource($this->rentalService->findForUser($id, $request->user()->id)));
    }

    public function quote(EquipmentRentalQuoteRequest $request): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success($this->rentalService->quote($data['start_date'], $data['end_date'], $data['items']));
    }

    public function store(CreateEquipmentRentalRequest $request): JsonResponse
    {
        $rental = $this->rentalService->create($request->user()->id, $request->validated());

        return ApiResponse::success(new EquipmentRentalResource($rental), 'Rental created.', 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $rental = $this->rentalService->cancelByCustomer($id, $request->user()->id);

        return ApiResponse::success(new EquipmentRentalResource($rental), 'Rental cancelled.');
    }
}
