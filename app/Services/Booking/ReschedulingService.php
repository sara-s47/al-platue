<?php

namespace App\Services\Booking;

use App\Enums\BookingChangeType;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\ReschedulePolicyRepositoryInterface;
use App\Services\Inventory\InventoryService;
use App\Services\Pricing\PricingService;
use App\Services\Studio\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReschedulingService
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository,
        protected ReschedulePolicyRepositoryInterface $policyRepository,
        protected AvailabilityService $availabilityService,
        protected InventoryService $inventoryService,
        protected PricingService $pricingService,
    ) {
    }

    public function validate(int $bookingId, Carbon $newStartAt, Carbon $newEndAt): array
    {
        $booking = $this->bookingRepository->findOrFail($bookingId);
        $policy = $this->getApplicablePolicy();
        $hoursUntilStart = Carbon::now()->diffInHours(Carbon::parse($booking->start_at), false);

        if ($hoursUntilStart < (int) $policy->min_notice_hours) {
            throw new BusinessException(
                "Reschedule requires at least {$policy->min_notice_hours} hours notice.",
                'reschedule_notice_violation',
            );
        }

        $rescheduleCount = DB::table('booking_changes')
            ->where('booking_id', $bookingId)
            ->where('type', BookingChangeType::Reschedule->value)
            ->count();

        if ($rescheduleCount >= (int) $policy->max_reschedules) {
            throw new BusinessException('Maximum reschedules reached for this booking.', 'max_reschedules_reached');
        }

        if (! in_array($booking->status, [
            BookingStatus::Confirmed,
            BookingStatus::Pending,
        ], true)) {
            throw new BusinessException('Booking cannot be rescheduled in its current state.', 'booking_not_reschedulable');
        }

        $this->availabilityService->validateSlot(
            (int) $booking->studio_id,
            $newStartAt,
            $newEndAt,
            (int) $booking->guest_count,
            $bookingId,
        );

        $equipment = $this->getBookingEquipment($bookingId);
        $hospitality = $this->getBookingHospitality($bookingId);

        $this->inventoryService->validateEquipmentAvailability(
            (int) $booking->studio_id,
            $equipment,
            $newStartAt,
            $newEndAt,
            $bookingId,
        );

        $this->inventoryService->validateHospitalityAvailability(
            (int) $booking->studio_id,
            $hospitality,
            $newStartAt,
            $newEndAt,
            $bookingId,
        );

        $oldQuote = $this->pricingService->calculateQuote(
            (int) $booking->studio_id,
            Carbon::parse($booking->start_at),
            Carbon::parse($booking->end_at),
            $equipment,
            $hospitality,
            $booking->package_id,
        );

        $newQuote = $this->pricingService->calculateQuote(
            (int) $booking->studio_id,
            $newStartAt,
            $newEndAt,
            $equipment,
            $hospitality,
            $booking->package_id,
        );

        $priceDifference = round($newQuote['total_amount'] - $oldQuote['total_amount'], 2);
        $fee = (float) $policy->fee;

        return [
            'price_difference' => $priceDifference,
            'fee' => $fee,
            'total_due' => max(0, $priceDifference + $fee),
        ];
    }

    public function execute(
        int $bookingId,
        Carbon $newStartAt,
        Carbon $newEndAt,
        ?int $userId = null,
        ?string $reason = null,
    ): Model {
        return DB::transaction(function () use ($bookingId, $newStartAt, $newEndAt, $userId, $reason) {
            $validation = $this->validate($bookingId, $newStartAt, $newEndAt);
            $booking = $this->bookingRepository->findOrFail($bookingId);

            $updated = $this->bookingRepository->update($bookingId, [
                'start_at' => $newStartAt,
                'end_at' => $newEndAt,
                'status' => BookingStatus::Rescheduled->value,
                'total_amount' => round((float) $booking->total_amount + $validation['price_difference'] + $validation['fee'], 2),
                'remaining_amount' => round((float) $booking->remaining_amount + $validation['total_due'], 2),
            ]);

            DB::table('booking_changes')->insert([
                'booking_id' => $bookingId,
                'type' => BookingChangeType::Reschedule->value,
                'old_start_at' => $booking->start_at,
                'old_end_at' => $booking->end_at,
                'new_start_at' => $newStartAt,
                'new_end_at' => $newEndAt,
                'fee' => $validation['fee'],
                'price_difference' => $validation['price_difference'],
                'reason' => $reason,
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            return $updated;
        });
    }

    protected function getApplicablePolicy(): object
    {
        $policy = DB::table('reschedule_policies')->where('is_active', true)->orderBy('id')->first();

        if (! $policy) {
            throw new BusinessException('No active reschedule policy configured.', 'reschedule_policy_missing');
        }

        return $policy;
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
