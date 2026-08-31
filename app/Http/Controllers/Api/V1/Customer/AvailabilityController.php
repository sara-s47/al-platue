<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Studio\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(protected AvailabilityService $availabilityService)
    {
    }

    public function index(Request $request, int $studioId): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'duration' => 'required|integer|min:1',
            'guest_count' => 'nullable|integer|min:1',
        ]);

        $slots = $this->availabilityService->getAvailableSlots(
            $studioId,
            $request->date,
            (int) $request->duration,
            $request->guest_count ? (int) $request->guest_count : null,
        );

        return ApiResponse::success($slots);
    }
}