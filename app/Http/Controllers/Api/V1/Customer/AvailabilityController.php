<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\Inventory\PackageService;
use App\Services\Studio\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected PackageService $packageService,
    ) {
    }

    public function index(Request $request, int $studioId): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'duration' => 'required_without:package_id|integer|min:1',
            'package_id' => 'nullable|integer',
            'guest_count' => 'nullable|integer|min:1',
        ]);

        $duration = (int) $request->duration;
        $enforceDurationLimits = true;

        // Package sessions have a fixed admin-defined length, so the hourly min/max limits do not apply (same as package holds).
        if ($request->filled('package_id')) {
            $package = $this->packageService->findOrFail((int) $request->package_id);

            if ((int) $package->studio_id !== $studioId) {
                throw new BusinessException('Package does not belong to this studio.', 'package_studio_mismatch');
            }

            $duration = (int) $package->duration_minutes;
            $enforceDurationLimits = false;
        }

        $result = $this->availabilityService->getAvailableSlots(
            $studioId,
            $request->date,
            $duration,
            $request->guest_count ? (int) $request->guest_count : null,
            enforceDurationLimits: $enforceDurationLimits,
        );

        // data stays a bare slots array for frontend compatibility; diagnostics go in meta.
        return ApiResponse::success($result['slots'], null, 200, $result['meta']);
    }

    public function daily(Request $request, int $studioId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'guest_count' => 'nullable|integer|min:1',
        ]);

        $result = $this->availabilityService->checkDailyAvailability(
            $studioId,
            $request->start_date,
            $request->end_date,
            $request->guest_count ? (int) $request->guest_count : 1,
        );

        return ApiResponse::success($result);
    }
}
