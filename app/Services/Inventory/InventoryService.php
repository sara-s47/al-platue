<?php

namespace App\Services\Inventory;

use App\Enums\BookingStatus;
use App\Enums\EquipmentRentalStatus;
use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessException;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Repositories\Contracts\HospitalityItemRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        protected EquipmentRepositoryInterface $equipmentRepository,
        protected HospitalityItemRepositoryInterface $hospitalityItemRepository,
    ) {
    }

    /**
     * @param  array<int, array{id: int, quantity: int}>  $equipment
     */
    public function validateEquipmentAvailability(
        int $studioId,
        array $equipment,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId = null,
    ): void {
        foreach ($equipment as $item) {
            $equipmentId = (int) $item['id'];
            $requestedQty = (int) $item['quantity'];

            if ($requestedQty < 1) {
                throw new BusinessException('Equipment quantity must be at least 1.', 'invalid_equipment_quantity');
            }

            $record = $this->equipmentRepository->findOrFail($equipmentId);

            if ($record->status !== EquipmentStatus::Active) {
                throw new BusinessException(
                    "Equipment {$record->name} is not available.",
                    'equipment_unavailable',
                    422,
                    null,
                    ['equipment_id' => $equipmentId],
                );
            }

            $stock = $this->getEquipmentStock($studioId, $equipmentId, (int) $record->quantity, $startAt, $endAt, $excludeBookingId);
            $context = [
                'reason' => $stock['reason'],
                'equipment_id' => $equipmentId,
                'studio_id' => $studioId,
                'scope' => $stock['scope'],
                'studio_quantity' => $stock['studio_quantity'],
                'allocated' => $stock['allocated'],
                'available' => $stock['available'],
                'requested' => $requestedQty,
            ];

            if ($stock['reason'] === 'not_assigned') {
                throw new BusinessException(
                    "Equipment {$record->name} is not assigned to this studio.",
                    'equipment_not_assigned',
                    422,
                    null,
                    $context,
                );
            }

            if ($stock['reason'] === 'studio_qty_zero') {
                throw new BusinessException(
                    "Equipment {$record->name} has zero quantity on this studio.",
                    'equipment_studio_qty_zero',
                    422,
                    null,
                    $context,
                );
            }

            if ($stock['available'] < $requestedQty) {
                $context['reason'] = $stock['available'] <= 0 ? 'fully_booked' : 'insufficient_quantity';

                throw new BusinessException(
                    "Insufficient quantity for equipment {$record->name}. Available: {$stock['available']}, requested: {$requestedQty}.",
                    'equipment_insufficient_quantity',
                    422,
                    null,
                    $context,
                );
            }
        }
    }

    /**
     * Equipment without studio links is shared by every studio and limited by its total quantity.
     * Linked equipment is limited to its studios and their per-studio quantity.
     * Either way, overlapping allocations across all studios can never exceed the total stock.
     *
     * @return array{scope: string, reason: string|null, studio_quantity: int, allocated: int, available: int}
     */
    public function getEquipmentStock(
        int $studioId,
        int $equipmentId,
        int $totalQuantity,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId = null,
    ): array {
        $links = DB::table('studio_equipment')
            ->where('equipment_id', $equipmentId)
            ->pluck('quantity', 'studio_id');

        $globalAllocated = $this->getAllocatedEquipmentQuantity($equipmentId, $startAt, $endAt, $excludeBookingId);
        $globalFree = max(0, $totalQuantity - $globalAllocated);

        if ($links->isEmpty()) {
            return [
                'scope' => 'all_studios',
                'reason' => $globalFree <= 0 ? 'fully_booked' : null,
                'studio_quantity' => $totalQuantity,
                'allocated' => $globalAllocated,
                'available' => $globalFree,
            ];
        }

        if (! $links->has($studioId)) {
            return [
                'scope' => 'assigned_studios',
                'reason' => 'not_assigned',
                'studio_quantity' => 0,
                'allocated' => 0,
                'available' => 0,
            ];
        }

        $studioQty = (int) $links->get($studioId);

        if ($studioQty === 0) {
            return [
                'scope' => 'assigned_studios',
                'reason' => 'studio_qty_zero',
                'studio_quantity' => 0,
                'allocated' => 0,
                'available' => 0,
            ];
        }

        $allocated = $this->getAllocatedEquipmentQuantity($equipmentId, $startAt, $endAt, $excludeBookingId, $studioId);
        $available = max(0, min($studioQty - $allocated, $globalFree));

        return [
            'scope' => 'assigned_studios',
            'reason' => $available <= 0 ? 'fully_booked' : null,
            'studio_quantity' => $studioQty,
            'allocated' => $allocated,
            'available' => $available,
        ];
    }

    /**
     * Helper for admin/support: equipment availability on a studio for a time range.
     *
     * @return array<string, mixed>
     */
    public function getEquipmentAvailabilityForStudio(
        int $studioId,
        int $equipmentId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId = null,
    ): array {
        $record = $this->equipmentRepository->findOrFail($equipmentId);

        return [
            'equipment_id' => $equipmentId,
            'name' => $record->name,
            ...$this->getEquipmentStock($studioId, $equipmentId, (int) $record->quantity, $startAt, $endAt, $excludeBookingId),
        ];
    }

    /**
     * @param  array<int, array{id: int, quantity: int}>  $hospitality
     */
    public function validateHospitalityAvailability(
        int $studioId,
        array $hospitality,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId = null,
    ): void {
        foreach ($hospitality as $item) {
            $itemId = (int) $item['id'];
            $requestedQty = (int) $item['quantity'];

            if ($requestedQty < 1) {
                throw new BusinessException('Hospitality quantity must be at least 1.', 'invalid_hospitality_quantity');
            }

            $record = $this->hospitalityItemRepository->findOrFail($itemId);

            if (! $record->is_active) {
                throw new BusinessException("Hospitality item {$record->name} is not available.", 'hospitality_unavailable');
            }

            $stock = $this->getHospitalityStock($studioId, $itemId, (int) $record->quantity, $startAt, $endAt, $excludeBookingId);

            if ($stock['reason'] === 'not_assigned') {
                throw new BusinessException(
                    "Hospitality item {$record->name} is not available at this studio.",
                    'hospitality_not_eligible',
                    422,
                    null,
                    ['reason' => 'not_assigned', 'hospitality_item_id' => $itemId, 'studio_id' => $studioId],
                );
            }

            if ($stock['available'] < $requestedQty) {
                throw new BusinessException(
                    "Insufficient quantity for {$record->name}. Available: {$stock['available']}, requested: {$requestedQty}.",
                    'hospitality_insufficient_quantity',
                    422,
                    null,
                    [
                        'reason' => $stock['available'] <= 0 ? 'fully_booked' : 'insufficient_quantity',
                        'hospitality_item_id' => $itemId,
                        'studio_id' => $studioId,
                        'scope' => $stock['scope'],
                        'total_quantity' => $stock['quantity'],
                        'allocated' => $stock['allocated'],
                        'available' => $stock['available'],
                        'requested' => $requestedQty,
                    ],
                );
            }
        }
    }

    /**
     * Items without studio links are offered in every studio; linked items only in their studios.
     * Each studio can use the item's full quantity, so only bookings in the same studio reduce it.
     *
     * @return array{scope: string, reason: string|null, quantity: int, allocated: int, available: int}
     */
    public function getHospitalityStock(
        int $studioId,
        int $itemId,
        int $totalQuantity,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId = null,
    ): array {
        $linkedStudioIds = DB::table('studio_hospitality')
            ->where('hospitality_item_id', $itemId)
            ->pluck('studio_id')
            ->map(fn ($id) => (int) $id);

        $scope = $linkedStudioIds->isEmpty() ? 'all_studios' : 'assigned_studios';

        if ($scope === 'assigned_studios' && ! $linkedStudioIds->contains($studioId)) {
            return ['scope' => $scope, 'reason' => 'not_assigned', 'quantity' => 0, 'allocated' => 0, 'available' => 0];
        }

        $allocated = $this->getAllocatedHospitalityQuantity($itemId, $startAt, $endAt, $excludeBookingId, $studioId);
        $available = max(0, $totalQuantity - $allocated);

        return [
            'scope' => $scope,
            'reason' => $available <= 0 ? 'fully_booked' : null,
            'quantity' => $totalQuantity,
            'allocated' => $allocated,
            'available' => $available,
        ];
    }

    /**
     * @param  array<int, array{id: int, quantity: int, unit_price: float, total_price: float}>  $equipment
     * @param  array<int, array{id: int, quantity: int, unit_price: float, total_price: float}>  $hospitality
     */
    public function allocateForBooking(int $bookingId, array $equipment, array $hospitality): void
    {
        $now = now();

        foreach ($equipment as $item) {
            DB::table('booking_equipment')->insert([
                'booking_id' => $bookingId,
                'equipment_id' => $item['id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($hospitality as $item) {
            DB::table('booking_hospitality')->insert([
                'booking_id' => $bookingId,
                'hospitality_item_id' => $item['id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function getAllocatedEquipmentQuantity(
        int $equipmentId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId,
        ?int $studioId = null,
    ): int {
        $fromBookings = (int) DB::table('booking_equipment')
            ->join('bookings', 'bookings.id', '=', 'booking_equipment.booking_id')
            ->where('booking_equipment.equipment_id', $equipmentId)
            ->when($studioId, fn ($q) => $q->where('bookings.studio_id', $studioId))
            ->whereIn('bookings.status', $this->blockingBookingStatuses())
            ->where('bookings.start_at', '<', $endAt)
            ->where('bookings.end_at', '>', $startAt)
            ->when($excludeBookingId, fn ($q) => $q->where('bookings.id', '!=', $excludeBookingId))
            ->sum('booking_equipment.quantity');

        // Standalone rentals are global (no studio); only count them in the global total.
        if ($studioId !== null) {
            return $fromBookings;
        }

        $fromRentals = (int) DB::table('equipment_rental_items')
            ->join('equipment_rentals', 'equipment_rentals.id', '=', 'equipment_rental_items.equipment_rental_id')
            ->where('equipment_rental_items.equipment_id', $equipmentId)
            ->whereIn('equipment_rentals.status', [
                EquipmentRentalStatus::Pending->value,
                EquipmentRentalStatus::Confirmed->value,
                EquipmentRentalStatus::InProgress->value,
            ])
            ->where('equipment_rentals.start_date', '<=', $endAt->copy()->subSecond()->toDateString())
            ->where('equipment_rentals.end_date', '>=', $startAt->toDateString())
            ->sum('equipment_rental_items.quantity');

        return $fromBookings + $fromRentals;
    }

    protected function getAllocatedHospitalityQuantity(
        int $itemId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId,
        int $studioId,
    ): int {
        return (int) DB::table('booking_hospitality')
            ->join('bookings', 'bookings.id', '=', 'booking_hospitality.booking_id')
            ->where('booking_hospitality.hospitality_item_id', $itemId)
            ->where('bookings.studio_id', $studioId)
            ->whereIn('bookings.status', $this->blockingBookingStatuses())
            ->where('bookings.start_at', '<', $endAt)
            ->where('bookings.end_at', '>', $startAt)
            ->when($excludeBookingId, fn ($q) => $q->where('bookings.id', '!=', $excludeBookingId))
            ->sum('booking_hospitality.quantity');
    }

    /**
     * @return list<string>
     */
    protected function blockingBookingStatuses(): array
    {
        return [
            BookingStatus::Pending->value,
            BookingStatus::Confirmed->value,
            BookingStatus::InProgress->value,
            BookingStatus::Extended->value,
            BookingStatus::Rescheduled->value,
        ];
    }
}
