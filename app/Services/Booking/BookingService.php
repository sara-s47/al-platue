<?php

namespace App\Services\Booking;

use App\Enums\BookingChangeType;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\BookingHoldRepositoryInterface;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\Content\AppSettingsService;
use App\Services\Inventory\InventoryService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Pricing\PricingService;
use App\Services\Promotion\PromoCodeService;
use App\Services\Studio\AvailabilityService;
use App\Services\Studio\HoldService;
use App\Support\BookingNumberGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository,
        protected BookingHoldRepositoryInterface $bookingHoldRepository,
        protected PricingService $pricingService,
        protected InventoryService $inventoryService,
        protected AvailabilityService $availabilityService,
        protected HoldService $holdService,
        protected PromoCodeService $promoCodeService,
        protected LoyaltyService $loyaltyService,
        protected CancellationService $cancellationService,
        protected ReschedulingService $reschedulingService,
        protected ExtensionService $extensionService,
        protected AppSettingsService $appSettings,
        protected BookingNumberGenerator $bookingNumberGenerator,
    ) {
    }

    /**
     * @param  array<int, array{id: int, quantity: int}>  $equipment
     * @param  array<int, array{id: int, quantity: int}>  $hospitality
     * @return array<string, mixed>
     */
    public function createQuote(
        int $userId,
        int $studioId,
        Carbon $startAt,
        Carbon $endAt,
        int $guestCount,
        array $equipment = [],
        array $hospitality = [],
        ?int $packageId = null,
        ?string $promoCode = null,
        ?int $pointsToRedeem = null,
    ): array {
        $this->availabilityService->validateSlot($studioId, $startAt, $endAt, $guestCount);
        $this->inventoryService->validateEquipmentAvailability($studioId, $equipment, $startAt, $endAt);
        $this->inventoryService->validateHospitalityAvailability($studioId, $hospitality, $startAt, $endAt);

        return $this->pricingService->calculateQuote(
            $studioId,
            $startAt,
            $endAt,
            $equipment,
            $hospitality,
            $packageId,
            $promoCode,
            $pointsToRedeem,
            $userId,
            $guestCount,
        );
    }

    /**
     * @param  array<int, array{id: int, quantity: int}>  $equipment
     * @param  array<int, array{id: int, quantity: int}>  $hospitality
     */
    public function createFromHold(
        int $holdId,
        int $userId,
        int $guestCount,
        array $equipment = [],
        array $hospitality = [],
        ?int $packageId = null,
        ?string $promoCode = null,
        ?int $pointsToRedeem = null,
        ?string $customerNotes = null,
    ): Model {
        return DB::transaction(function () use (
            $holdId,
            $userId,
            $guestCount,
            $equipment,
            $hospitality,
            $packageId,
            $promoCode,
            $pointsToRedeem,
            $customerNotes,
        ) {
            $hold = $this->bookingHoldRepository->findOrFail($holdId);

            if ((int) $hold->user_id !== $userId) {
                throw new BusinessException('Hold does not belong to this user.', 'hold_unauthorized');
            }

            $startAt = Carbon::parse($hold->start_at);
            $endAt = Carbon::parse($hold->end_at);
            $studioId = (int) $hold->studio_id;

            $quote = $this->createQuote(
                $userId,
                $studioId,
                $startAt,
                $endAt,
                $guestCount,
                $equipment,
                $hospitality,
                $packageId,
                $promoCode,
                $pointsToRedeem,
            );

            $booking = $this->bookingRepository->create([
                'booking_number' => $this->bookingNumberGenerator->generate(),
                'user_id' => $userId,
                'studio_id' => $studioId,
                'package_id' => $packageId,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'guest_count' => $guestCount,
                'status' => BookingStatus::Pending->value,
                'subtotal' => $quote['subtotal'],
                'discount_amount' => $quote['discount_amount'],
                'points_discount' => $quote['points_discount'],
                'tax_amount' => $quote['tax_amount'],
                'total_amount' => $quote['total_amount'],
                'paid_amount' => 0,
                'remaining_amount' => $quote['total_amount'],
                'customer_notes' => $customerNotes,
            ]);

            $this->persistLineItems($booking->id, $quote);
            $this->inventoryService->allocateForBooking(
                $booking->id,
                $this->mapEquipmentForAllocation($quote['line_items']),
                $this->mapHospitalityForAllocation($quote['line_items']),
            );

            if ($promoCode && isset($quote['promo']['promo_code_id'])) {
                $this->promoCodeService->apply(
                    (int) $quote['promo']['promo_code_id'],
                    $userId,
                    $booking->id,
                    (float) $quote['discount_amount'],
                );
            }

            if ($pointsToRedeem) {
                $this->loyaltyService->redeemPoints($userId, $pointsToRedeem, $booking->id);
            }

            $this->holdService->convertHold($holdId, $booking->id);

            return $booking->fresh();
        });
    }

    public function confirmBooking(int $bookingId): Model
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = $this->bookingRepository->findOrFail($bookingId);

            if ($booking->status !== BookingStatus::Pending->value) {
                throw new BusinessException('Only pending bookings can be confirmed.', 'invalid_booking_status');
            }

            $startAt = Carbon::parse($booking->start_at);
            $endAt = Carbon::parse($booking->end_at);

            $this->availabilityService->validateSlot(
                (int) $booking->studio_id,
                $startAt,
                $endAt,
                (int) $booking->guest_count,
                $bookingId,
            );

            $equipment = $this->getBookingEquipment($bookingId);
            $hospitality = $this->getBookingHospitality($bookingId);

            $this->inventoryService->validateEquipmentAvailability(
                (int) $booking->studio_id,
                $equipment,
                $startAt,
                $endAt,
                $bookingId,
            );

            $this->inventoryService->validateHospitalityAvailability(
                (int) $booking->studio_id,
                $hospitality,
                $startAt,
                $endAt,
                $bookingId,
            );

            return $this->bookingRepository->update($bookingId, [
                'status' => BookingStatus::Confirmed->value,
            ]);
        });
    }

    public function cancel(int $bookingId, ?int $userId = null, ?string $reason = null): Model
    {
        return DB::transaction(function () use ($bookingId, $userId, $reason) {
            $booking = $this->bookingRepository->findOrFail($bookingId);

            if (in_array($booking->status, [
                BookingStatus::Cancelled->value,
                BookingStatus::Completed->value,
            ], true)) {
                throw new BusinessException('Booking cannot be cancelled.', 'booking_not_cancellable');
            }

            $refund = $this->cancellationService->calculateRefund($booking);

            $updated = $this->bookingRepository->update($bookingId, [
                'status' => BookingStatus::Cancelled->value,
            ]);

            DB::table('booking_changes')->insert([
                'booking_id' => $bookingId,
                'type' => BookingChangeType::Cancellation->value,
                'old_start_at' => $booking->start_at,
                'old_end_at' => $booking->end_at,
                'fee' => $refund['cancellation_fee'],
                'price_difference' => -$refund['refund_amount'],
                'reason' => $reason,
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            if ($booking->points_discount > 0) {
                $this->loyaltyService->reversePoints((int) $booking->user_id, $bookingId, 'Booking cancelled');
            }

            return $updated;
        });
    }

    public function reschedule(
        int $bookingId,
        Carbon $newStartAt,
        Carbon $newEndAt,
        ?int $userId = null,
        ?string $reason = null,
    ): Model {
        return $this->reschedulingService->execute($bookingId, $newStartAt, $newEndAt, $userId, $reason);
    }

    public function extend(int $bookingId, Carbon $newEndAt, ?int $userId = null): Model
    {
        return $this->extensionService->execute($bookingId, $newEndAt, $userId);
    }

    public function bookAgain(int $bookingId, Carbon $startAt, ?int $userId = null): Model
    {
        return DB::transaction(function () use ($bookingId, $startAt, $userId) {
            $original = $this->bookingRepository->findOrFail($bookingId);
            $durationMinutes = Carbon::parse($original->start_at)->diffInMinutes(Carbon::parse($original->end_at));
            $endAt = $startAt->copy()->addMinutes($durationMinutes);

            $equipment = $this->getBookingEquipment($bookingId);
            $hospitality = $this->getBookingHospitality($bookingId);

            $hold = $this->holdService->createHold(
                $userId ?? (int) $original->user_id,
                (int) $original->studio_id,
                $startAt,
                $endAt,
            );

            return $this->createFromHold(
                $hold->id,
                $userId ?? (int) $original->user_id,
                (int) $original->guest_count,
                $equipment,
                $hospitality,
                $original->package_id,
            );
        });
    }

    public function updateStatus(int $bookingId, BookingStatus $status): Model
    {
        $booking = $this->bookingRepository->findOrFail($bookingId);
        $this->assertValidStatusTransition(BookingStatus::from($booking->status), $status);

        $updated = $this->bookingRepository->update($bookingId, ['status' => $status->value]);

        if ($status === BookingStatus::Completed) {
            $this->loyaltyService->earnFromBooking($updated);
        }

        return $updated;
    }

    public function addInternalNote(int $bookingId, int $adminUserId, string $note): void
    {
        DB::table('booking_internal_notes')->insert([
            'booking_id' => $bookingId,
            'user_id' => $adminUserId,
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function assertValidStatusTransition(BookingStatus $from, BookingStatus $to): void
    {
        $allowed = [
            BookingStatus::Pending->value => [BookingStatus::Confirmed, BookingStatus::Cancelled],
            BookingStatus::Confirmed->value => [BookingStatus::InProgress, BookingStatus::Cancelled, BookingStatus::NoShow],
            BookingStatus::InProgress->value => [BookingStatus::Completed, BookingStatus::Extended],
            BookingStatus::Extended->value => [BookingStatus::Completed],
        ];

        $permitted = $allowed[$from->value] ?? [];

        if (! in_array($to, $permitted, true)) {
            throw new BusinessException(
                "Cannot transition booking from {$from->value} to {$to->value}.",
                'invalid_status_transition',
            );
        }
    }

    protected function persistLineItems(int $bookingId, array $quote): void
    {
        $now = now();

        foreach ($quote['line_items'] as $item) {
            DB::table('booking_price_snapshots')->insert([
                'booking_id' => $bookingId,
                'item_type' => $item['item_type'],
                'item_id' => $item['item_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_amount' => $item['discount_amount'] ?? 0,
                'total_price' => $item['total_price'],
                'created_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, array{id: int, quantity: int, unit_price: float, total_price: float}>
     */
    protected function mapEquipmentForAllocation(array $lineItems): array
    {
        return collect($lineItems)
            ->where('item_type', 'equipment')
            ->map(fn ($item) => [
                'id' => $item['item_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, array{id: int, quantity: int, unit_price: float, total_price: float}>
     */
    protected function mapHospitalityForAllocation(array $lineItems): array
    {
        return collect($lineItems)
            ->where('item_type', 'hospitality')
            ->map(fn ($item) => [
                'id' => $item['item_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
            ])
            ->values()
            ->all();
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
