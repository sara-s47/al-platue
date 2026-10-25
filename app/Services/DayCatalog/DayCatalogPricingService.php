<?php

namespace App\Services\DayCatalog;

use App\Support\DayCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DayCatalogPricingService
{
    public function __construct(
        protected DayCatalogInventoryService $inventoryService,
    ) {
    }

    /**
     * Price lines use the product's current price_per_day; callers persist it as the snapshot.
     *
     * @param  array<int, array{id: int, quantity: int}>  $items
     * @return array{lines: list<array<string, mixed>>, subtotal: float}
     */
    public function buildLines(string $type, array $items, int $days): array
    {
        $modelClass = DayCatalog::modelClass($type);
        $lines = [];
        $subtotal = 0.0;

        foreach ($this->inventoryService->groupItems($items) as $productId => $quantity) {
            /** @var Model $product */
            $product = $modelClass::query()->findOrFail($productId);
            $unitPrice = (float) $product->price_per_day;
            $total = round($unitPrice * $quantity * $days, 2);

            $lines[] = [
                'item_type' => $type,
                'item_id' => $product->id,
                'description' => $product->name,
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
