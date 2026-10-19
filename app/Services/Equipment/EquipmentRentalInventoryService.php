<?php

namespace App\Services\Equipment;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\RentalOrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Equipment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EquipmentRentalInventoryService
{
    /**
     * @param  array<int, array{id: int, quantity: int}>  $items
     */
    public function validateAvailability(
        array $items,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeRentalId = null,
        ?int $excludeBookingId = null,
    ): void {
        foreach ($this->groupItems($items) as $equipmentId => $requested) {
            if ($requested < 1) {
                throw new BusinessException('Equipment quantity must be at least 1.', 'invalid_quantity');
            }

            $equipment = Equipment::query()->find($equipmentId);

            if (! $equipment || $equipment->status !== EquipmentStatus::Active) {
                throw new BusinessException(
                    'Equipment is not available.',
                    'equipment_not_available',
                    422,
                    null,
                    ['equipment_id' => $equipmentId],
                );
            }

            $allocated = $this->getAllocatedQuantity($equipmentId, $startAt, $endAt, $excludeRentalId, $excludeBookingId);
            $available = max(0, (int) $equipment->quantity - $allocated);

            if ($requested > $available) {
                throw new BusinessException(
                    "Not enough \"{$equipment->name}\" available for the selected period.",
                    'equipment_insufficient_quantity',
                    422,
                    null,
                    [
                        'equipment_id' => $equipmentId,
                        'stock' => (int) $equipment->quantity,
                        'allocated' => $allocated,
                        'available' => $available,
                        'requested' => $requested,
                    ],
                );
            }
        }
    }

    public function getAllocatedQuantity(
        int $equipmentId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeRentalId = null,
        ?int $excludeBookingId = null,
    ): int {
        $fromBookings = (int) DB::table('booking_equipment')
            ->join('bookings', 'bookings.id', '=', 'booking_equipment.booking_id')
            ->where('booking_equipment.equipment_id', $equipmentId)
            ->whereIn('bookings.status', $this->blockingBookingStatuses())
            ->where('bookings.start_at', '<', $endAt)
            ->where('bookings.end_at', '>', $startAt)
            ->when($excludeBookingId, fn ($q) => $q->where('bookings.id', '!=', $excludeBookingId))
            ->sum('booking_equipment.quantity');

        $fromOrders = (int) DB::table('equipment_rental_items')
            ->join('rental_orders', 'rental_orders.id', '=', 'equipment_rental_items.rental_order_id')
            ->where('equipment_rental_items.equipment_id', $equipmentId)
            ->whereNotNull('equipment_rental_items.rental_order_id')
            ->whereIn('rental_orders.status', $this->blockingRentalStatuses())
            ->where('rental_orders.start_date', '<=', $endAt->copy()->subSecond()->toDateString())
            ->where('rental_orders.end_date', '>=', $startAt->toDateString())
            ->when($excludeRentalId, fn ($q) => $q->where('rental_orders.id', '!=', $excludeRentalId))
            ->sum('equipment_rental_items.quantity');

        return $fromBookings + $fromOrders;
    }

    /**
     * @return array<int, int>
     */
    public function availableQuantities(Carbon $startAt, Carbon $endAt): array
    {
        return Equipment::query()
            ->where('status', EquipmentStatus::Active)
            ->get(['id', 'quantity'])
            ->mapWithKeys(fn (Equipment $equipment) => [
                $equipment->id => max(0, (int) $equipment->quantity - $this->getAllocatedQuantity($equipment->id, $startAt, $endAt)),
            ])
            ->all();
    }

    /**
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
            RentalOrderStatus::Pending->value,
            RentalOrderStatus::Confirmed->value,
            RentalOrderStatus::InProgress->value,
        ];
    }
}
