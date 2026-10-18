<?php

namespace App\Services\Equipment;

use App\Repositories\Contracts\EquipmentRepositoryInterface;

class EquipmentRentalPricingService
{
    public function __construct(
        protected EquipmentRepositoryInterface $equipmentRepository,
        protected EquipmentRentalInventoryService $inventoryService,
    ) {
    }

    /**
     * Standalone rentals use price_per_day (falls back to price).
     *
     * @param  array<int, array{id: int, quantity: int}>  $items
     * @return array{lines: list<array<string, mixed>>, subtotal: float}
     */
    public function buildLines(array $items, int $days): array
    {
        $lines = [];
        $subtotal = 0.0;

        foreach ($this->inventoryService->groupItems($items) as $equipmentId => $quantity) {
            $equipment = $this->equipmentRepository->findOrFail($equipmentId);
            $unitPrice = (float) ($equipment->price_per_day ?? $equipment->price);
            $total = round($unitPrice * $quantity * $days, 2);

            $lines[] = [
                'item_type' => 'equipment',
                'item_id' => $equipment->id,
                'description' => $equipment->name,
                'quantity' => $quantity,
                'days' => $days,
                'unit_price' => $unitPrice,
                'discount_amount' => 0,
                'total_price' => $total,
                'included' => false,
            ];

            $subtotal += $total;
        }

        return [
            'lines' => $lines,
            'subtotal' => round($subtotal, 2),
        ];
    }
}
