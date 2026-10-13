<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\AccessoryRentalQuoteRequest;
use App\Http\Requests\Api\V1\Customer\CreateAccessoryRentalRequest;
use App\Http\Resources\Api\V1\AccessoryRentalResource;
use App\Http\Responses\ApiResponse;
use App\Services\Accessory\AccessoryRentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessoryRentalController extends Controller
{
    public function __construct(protected AccessoryRentalService $rentalService) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->rentalService->paginateForUser($request->user()->id, (int) $request->get('per_page', 15));

        return ApiResponse::paginated($paginator->through(fn ($r) => new AccessoryRentalResource($r)));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return ApiResponse::success(new AccessoryRentalResource($this->rentalService->findForUser($id, $request->user()->id)));
    }

    public function quote(AccessoryRentalQuoteRequest $request): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success($this->rentalService->quote($data['start_date'], $data['end_date'], $data['items']));
    }

    public function store(CreateAccessoryRentalRequest $request): JsonResponse
    {
        $rental = $this->rentalService->create($request->user()->id, $request->validated());

        return ApiResponse::success(new AccessoryRentalResource($rental), 'Rental created.', 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $rental = $this->rentalService->cancelByCustomer($id, $request->user()->id);

        return ApiResponse::success(new AccessoryRentalResource($rental), 'Rental cancelled.');
    }
}
