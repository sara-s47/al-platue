<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\CreateHoldRequest;
use App\Http\Resources\Api\V1\BookingHoldResource;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\HoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingHoldController extends Controller
{
    public function __construct(protected HoldService $holdService)
    {
    }

    public function store(CreateHoldRequest $request): JsonResponse
    {
        $hold = $this->holdService->createHold($request->user(), $request->validated());

        return ApiResponse::success(new BookingHoldResource($hold), 'Hold created.', 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $hold = $this->holdService->releaseHold($id, $request->user());

        return ApiResponse::success(new BookingHoldResource($hold), 'Hold released.');
    }
}