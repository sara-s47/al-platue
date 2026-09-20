<?php

namespace App\Services\Inventory;

use App\Enums\BookingStatus;
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

            $studioQty = $this->getStudioEquipmentQuantity($studioId, $equipmentId);

            if ($studioQty === null) {
                throw new BusinessException(
                    "Equipment {$record->name} is not assigned to this studio.",
                    'equipment_not_assigned',
                    422,
                    null,
                    [
                        'reason' => 'not_assigned',
                        'equipment_id' => $equipmentId,
                        'studio_id' => $studioId,
                        'studio_quantity' => 0,
                        'allocated' => 0,
                        'available' => 0,
                        'requested' => $requestedQty,
                    ],
                );
            }

            if ($studioQty === 0) {
                throw new BusinessException(
                    "Equipment {$record->name} has zero quantity on this studio.",
                    'equipment_studio_qty_zero',
                    422,
                    null,
                    [
                        'reason' => 'studio_qty_zero',
                        'equipment_id' => $equipmentId,
                        'studio_id' => $studioId,
                        'studio_quantity' => 0,
                        'allocated' => 0,
                        'available' => 0,
                        'requested' => $requestedQty,
                    ],
                );
            }

            $allocated = $this->getAllocatedEquipmentQuantity($equipmentId, $startAt, $endAt, $excludeBookingId, $studioId);
            $available = max(0, $studioQty - $allocated);

            if ($available < $requestedQty) {
                throw new BusinessException(
                    "Insufficient quantity for equipment {$record->name}. Available: {$available}, requested: {$requestedQty}.",
                    'equipment_insufficient_quantity',
                    422,
                    null,
                    [
                        'reason' => $available <= 0 ? 'fully_booked' : 'insufficient_quantity',
                        'equipment_id' => $equipmentId,
                        'studio_id' => $studioId,
                        'studio_quantity' => $studioQty,
                        'allocated' => $allocated,
                        'available' => $available,
                        'requested' => $requestedQty,
                    ],
                );
            }
        }
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
        $studioQty = $this->getStudioEquipmentQuantity($studioId, $equipmentId);

        if ($studioQty === null) {
            return [
                'equipment_id' => $equipmentId,
                'name' => $record->name,
                'reason' => 'not_assigned',
                'studio_quantity' => 0,
                'allocated' => 0,
                'available' => 0,
            ];
        }

        $allocated = $this->getAllocatedEquipmentQuantity($equipmentId, $startAt, $endAt, $excludeBookingId, $studioId);

        return [
            'equipment_id' => $equipmentId,
            'name' => $record->name,
            'reason' => $studioQty === 0 ? 'studio_qty_zero' : null,
            'studio_quantity' => $studioQty,
            'allocated' => $allocated,
            'available' => max(0, $studioQty - $allocated),
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

            if (! $this->isHospitalityAssignedToStudio($studioId, $itemId)) {
                throw new BusinessException("Hospitality item {$record->name} is not available at this studio.", 'hospitality_not_eligible');
            }

            $allocated = $this->getAllocatedHospitalityQuantity($itemId, $startAt, $endAt, $excludeBookingId);
            $available = (int) $record->quantity - $allocated;

            if ($available < $requestedQty) {
                throw new BusinessException(
                    "Insufficient quantity for {$record->name}. Available: {$available}, requested: {$requestedQty}.",
                    'hospitality_insufficient_quantity',
                    422,
                    null,
                    [
                        'hospitality_item_id' => $itemId,
                        'total_quantity' => (int) $record->quantity,
                        'allocated' => $allocated,
                        'available' => max(0, $available),
                        'requested' => $requestedQty,
                    ],
                );
            }
        }
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

    protected function getStudioEquipmentQuantity(int $studioId, int $equipmentId): ?int
    {
        $row = DB::table('studio_equipment')
            ->where('studio_id', $studioId)
            ->where('equipment_id', $equipmentId)
            ->first();

        return $row ? (int) $row->quantity : null;
    }

    protected function isHospitalityAssignedToStudio(int $studioId, int $itemId): bool
    {
        return DB::table('studio_hospitality')
            ->where('studio_id', $studioId)
            ->where('hospitality_item_id', $itemId)
            ->exists();
    }

    protected function getAllocatedEquipmentQuantity(
        int $equipmentId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId,
        ?int $studioId = null,
    ): int {
        return (int) DB::table('booking_equipment')
            ->join('bookings', 'bookings.id', '=', 'booking_equipment.booking_id')
            ->where('booking_equipment.equipment_id', $equipmentId)
            ->when($studioId, fn ($q) => $q->where('bookings.studio_id', $studioId))
            ->whereIn('bookings.status', $this->blockingBookingStatuses())
            ->where('bookings.start_at', '<', $endAt)
            ->where('bookings.end_at', '>', $startAt)
            ->when($excludeBookingId, fn ($q) => $q->where('bookings.id', '!=', $excludeBookingId))
            ->sum('booking_equipment.quantity');
    }

    protected function getAllocatedHospitalityQuantity(
        int $itemId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeBookingId,
    ): int {
        return (int) DB::table('booking_hospitality')
            ->join('bookings', 'bookings.id', '=', 'booking_hospitality.booking_id')
            ->where('booking_hospitality.hospitality_item_id', $itemId)
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
        ];
    }
}
