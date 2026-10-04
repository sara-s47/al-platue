<?php

namespace App\Services\Accessory;

use App\Repositories\Contracts\AccessoryRepositoryInterface;
use Carbon\Carbon;

class AccessoryPricingService
{
    public function __construct(
        protected AccessoryRepositoryInterface $accessoryRepository,
        protected AccessoryInventoryService $inventoryService,
    ) {
    }

    /**
     * Price lines use the accessory's current price_per_day; callers persist it as the snapshot.
     *
     * @param  array<int, array{id: int, quantity: int}>  $items
     * @return array{lines: list<array<string, mixed>>, subtotal: float}
     */
    public function buildLines(array $items, int $days): array
    {
        $lines = [];
        $subtotal = 0.0;

        foreach ($this->inventoryService->groupItems($items) as $accessoryId => $quantity) {
            $accessory = $this->accessoryRepository->findOrFail($accessoryId);
            $unitPrice = (float) $accessory->price_per_day;
            $total = round($unitPrice * $quantity * $days, 2);

            $lines[] = [
                'item_type' => 'accessory',
                'item_id' => $accessory->id,
                'description' => $accessory->name,
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

    /**
     * Calendar days touched by a booking (an hourly booking within one day counts as 1).
     */
    public function calendarDaysSpanned(Carbon $startAt, Carbon $endAt): int
    {
        $lastDay = $endAt->copy()->subSecond()->startOfDay();

        return max(1, (int) $startAt->copy()->startOfDay()->diffInDays($lastDay) + 1);
    }
}
