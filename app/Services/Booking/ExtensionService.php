<?php

namespace App\Services\Booking;

use App\Enums\BookingChangeType;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Inventory\InventoryService;
use App\Services\Pricing\PricingService;
use App\Services\Studio\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ExtensionService
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository,
        protected AvailabilityService $availabilityService,
        protected InventoryService $inventoryService,
        protected PricingService $pricingService,
    ) {
    }

    public function validate(int $bookingId, Carbon $newEndAt): array
    {
        $booking = $this->bookingRepository->findOrFail($bookingId);
        $currentEnd = Carbon::parse($booking->end_at);

        if ($newEndAt->lte($currentEnd)) {
            throw new BusinessException('Extension end time must be after current end time.', 'invalid_extension_time');
        }

        if (! in_array($booking->status, [
            BookingStatus::Confirmed,
            BookingStatus::InProgress,
            BookingStatus::Extended,
        ], true)) {
            throw new BusinessException('Booking cannot be extended in its current state.', 'booking_not_extendable');
        }

        $this->availabilityService->validateSlot(
            (int) $booking->studio_id,
            $currentEnd,
            $newEndAt,
            (int) $booking->guest_count,
            $bookingId,
            false,
        );

        $equipment = $this->getBookingEquipment($bookingId);
        $hospitality = $this->getBookingHospitality($bookingId);

        $this->inventoryService->validateEquipmentAvailability(
            (int) $booking->studio_id,
            $equipment,
            $currentEnd,
            $newEndAt,
            $bookingId,
        );

        $this->inventoryService->validateHospitalityAvailability(
            (int) $booking->studio_id,
            $hospitality,
            $currentEnd,
            $newEndAt,
            $bookingId,
        );

        $extensionQuote = $this->pricingService->calculateQuote(
            (int) $booking->studio_id,
            $currentEnd,
            $newEndAt,
            $equipment,
            $hospitality,
            $booking->package_id,
        );

        return [
            'additional_amount' => $extensionQuote['total_amount'],
            'new_end_at' => $newEndAt->toIso8601String(),
        ];
    }

    public function execute(int $bookingId, Carbon $newEndAt, ?int $userId = null): Model
    {
        return DB::transaction(function () use ($bookingId, $newEndAt, $userId) {
            $validation = $this->validate($bookingId, $newEndAt);
            $booking = $this->bookingRepository->findOrFail($bookingId);

            $updated = $this->bookingRepository->update($bookingId, [
                'end_at' => $newEndAt,
                'status' => BookingStatus::Extended->value,
                'subtotal' => round((float) $booking->subtotal + $validation['additional_amount'], 2),
                'total_amount' => round((float) $booking->total_amount + $validation['additional_amount'], 2),
                'remaining_amount' => round((float) $booking->remaining_amount + $validation['additional_amount'], 2),
            ]);

            DB::table('booking_changes')->insert([
                'booking_id' => $bookingId,
                'type' => BookingChangeType::Extension->value,
                'old_start_at' => $booking->start_at,
                'old_end_at' => $booking->end_at,
                'new_start_at' => $booking->start_at,
                'new_end_at' => $newEndAt,
                'price_difference' => $validation['additional_amount'],
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            return $updated;
        });
    }

    /**
     * @return array<int, array{id: int, quantity: int}>
     */
    protected function getBookingEquipment(int $bookingId): array
    {
        return DB::table('booking_equipment')
            ->where('booking_id', $bookingId)
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->equipment_id, 'quantity' => (int) $row->quantity])
            ->all();
    }

    /**
     * @return array<int, array{id: int, quantity: int}>
     */
    protected function getBookingHospitality(int $bookingId): array
    {
        return DB::table('booking_hospitality')
            ->where('booking_id', $bookingId)
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->hospitality_item_id, 'quantity' => (int) $row->quantity])
            ->all();
    }
}
