<?php

namespace App\Services\Accessory;

use App\Enums\AccessoryRentalStatus;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessException;
use App\Models\Accessory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccessoryInventoryService
{
    /**
     * Throws when any requested accessory does not have enough free stock in the period.
     *
     * @param  array<int, array{id: int, quantity: int}>  $items
     */
    public function validateAvailability(
        array $items,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId = null,
        ?int $excludeRentalId = null,
    ): void {
        foreach ($this->groupItems($items) as $accessoryId => $requested) {
            if ($requested < 1) {
                throw new BusinessException('Accessory quantity must be at least 1.', 'invalid_quantity');
            }

            $accessory = Accessory::query()->find($accessoryId);

            if (! $accessory || ! $accessory->is_active) {
                throw new BusinessException(
                    'Accessory is not available.',
                    'accessory_not_available',
                    422,
                    null,
                    ['accessory_id' => $accessoryId],
                );
            }

            $allocated = $this->getAllocatedQuantity($accessoryId, $startAt, $endAt, $excludeBookingId, $excludeRentalId);
            $available = max(0, (int) $accessory->quantity - $allocated);

            if ($requested > $available) {
                throw new BusinessException(
                    "Not enough \"{$accessory->name}\" available for the selected period.",
                    'accessory_insufficient_quantity',
                    422,
                    null,
                    [
                        'accessory_id' => $accessoryId,
                        'stock' => (int) $accessory->quantity,
                        'allocated' => $allocated,
                        'available' => $available,
                        'requested' => $requested,
                    ],
                );
            }
        }
    }

    public function getAllocatedQuantity(
        int $accessoryId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId = null,
        ?int $excludeRentalId = null,
    ): int {
        $fromBookings = DB::table('accessory_rental_items')
            ->join('bookings', 'bookings.id', '=', 'accessory_rental_items.booking_id')
            ->where('accessory_rental_items.accessory_id', $accessoryId)
            ->whereIn('bookings.status', $this->blockingBookingStatuses())
            ->where('bookings.start_at', '<', $endAt)
            ->where('bookings.end_at', '>', $startAt)
            ->when($excludeBookingId, fn ($q) => $q->where('bookings.id', '!=', $excludeBookingId))
            ->sum('accessory_rental_items.quantity');

        // Standalone rentals cover whole days, so compare by date.
        $fromRentals = DB::table('accessory_rental_items')
            ->join('accessory_rentals', 'accessory_rentals.id', '=', 'accessory_rental_items.accessory_rental_id')
            ->where('accessory_rental_items.accessory_id', $accessoryId)
            ->whereIn('accessory_rentals.status', $this->blockingRentalStatuses())
            ->where('accessory_rentals.start_date', '<=', $endAt->copy()->subSecond()->toDateString())
            ->where('accessory_rentals.end_date', '>=', $startAt->toDateString())
            ->when($excludeRentalId, fn ($q) => $q->where('accessory_rentals.id', '!=', $excludeRentalId))
            ->sum('accessory_rental_items.quantity');

        return (int) $fromBookings + (int) $fromRentals;
    }

    /**
     * @return array<int, int> accessory id => free quantity in the period
     */
    public function availableQuantities(Carbon $startAt, Carbon $endAt): array
    {
        return Accessory::query()
            ->where('is_active', true)
            ->get(['id', 'quantity'])
            ->mapWithKeys(fn (Accessory $accessory) => [
                $accessory->id => max(0, (int) $accessory->quantity - $this->getAllocatedQuantity($accessory->id, $startAt, $endAt)),
            ])
            ->all();
    }

    /**
     * Whole-day window for a standalone rental: start of start_date to start of the day after end_date.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rentalPeriod(string $startDate, string $endDate): array
    {
        return [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->addDay()->startOfDay(),
        ];
    }

    /**
     * @param  array<int, array{id: int, quantity: int}>  $items
     * @return array<int, int>
     */
    public function groupItems(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $id = (int) $item['id'];
            $grouped[$id] = ($grouped[$id] ?? 0) + (int) $item['quantity'];
        }

        return $grouped;
    }

    /**
     * @return list<string>
     */
    public function blockingBookingStatuses(): array
    {
        return [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
            BookingStatus::InProgress->value,
            BookingStatus::Extended->value,
            BookingStatus::Rescheduled->value,
        ];
    }

    /**
     * @return list<string>
     */
    public function blockingRentalStatuses(): array
    {
        return [
            AccessoryRentalStatus::Pending->value,
            AccessoryRentalStatus::Confirmed->value,
            AccessoryRentalStatus::InProgress->value,
        ];
    }
}
