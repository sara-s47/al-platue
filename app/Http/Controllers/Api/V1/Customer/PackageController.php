<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\BookingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\PackageHoldRequest;
use App\Http\Requests\Api\V1\Customer\PackageQuoteRequest;
use App\Http\Resources\Api\V1\BookingHoldResource;
use App\Http\Resources\Api\V1\PackageResource;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Http\Responses\ApiResponse;
use App\Services\Booking\BookingService;
use App\Services\Inventory\PackageService;
use App\Services\Studio\HoldService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class PackageController extends Controller
{
    public function __construct(
        protected PackageService $packageService,
        protected BookingService $bookingService,
        protected HoldService $holdService,
    ) {
    }

    public function index(int $studioId): JsonResponse
    {
        return ApiResponse::success(PackageResource::collection($this->packageService->getForStudio($studioId)));
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::success(new PackageResource($this->packageService->findWithDetails($id)));
    }

    public function quote(PackageQuoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $package = $this->packageService->findOrFail((int) $data['package_id']);
        $startAt = Carbon::parse($data['start_at']);
        $endAt = $startAt->copy()->addMinutes((int) $package->duration_minutes);

        $quote = $this->bookingService->createQuote(
            $request->user()->id,
            (int) $package->studio_id,
            $startAt,
            $endAt,
            (int) $data['guest_count'],
            $data['equipment'] ?? [],
            $data['hospitality'] ?? [],
            (int) $package->id,
            $data['promo_code'] ?? null,
            $data['points_to_redeem'] ?? null,
            BookingMode::Hourly,
        );

        return ApiResponse::success(new QuoteResource($quote));
    }

    public function hold(PackageHoldRequest $request): JsonResponse
    {
        $data = $request->validated();
        $package = $this->packageService->findOrFail((int) $data['package_id']);
        $this->packageService->validatePackageItems((int) $package->id, (int) $package->studio_id);

        $startAt = Carbon::parse($data['start_at']);
        $endAt = $startAt->copy()->addMinutes((int) $package->duration_minutes);

        $equipmentMerge = $this->packageService->mergeInventoryItems(
            $data['equipment'] ?? [],
            $this->packageService->getIncludedEquipment((int) $package->id),
        );
        $hospitalityMerge = $this->packageService->mergeInventoryItems(
            $data['hospitality'] ?? [],
            $this->packageService->getIncludedHospitality((int) $package->id),
        );

        $hold = $this->holdService->createHold($request->user(), [
            'studio_id' => (int) $package->studio_id,
            'booking_mode' => BookingMode::Hourly->value,
            'start_at' => $startAt->toDateTimeString(),
            'end_at' => $endAt->toDateTimeString(),
            'guest_count' => (int) ($data['guest_count'] ?? 1),
            'equipment_ids' => collect($equipmentMerge['merged'])
                ->mapWithKeys(fn ($item) => [$item['id'] => $item['quantity']])
                ->all(),
            'hospitality_ids' => collect($hospitalityMerge['merged'])
                ->mapWithKeys(fn ($item) => [$item['id'] => $item['quantity']])
                ->all(),
            'package_id' => (int) $package->id,
            'skip_duration_limits' => true,
        ]);

        return ApiResponse::success([
            'hold' => new BookingHoldResource($hold),
            'package_id' => (int) $package->id,
            'duration_minutes' => (int) $package->duration_minutes,
        ], 'Package hold created.', 201);
    }
}
