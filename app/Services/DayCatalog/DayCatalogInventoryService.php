<?php

namespace App\Services\DayCatalog;

use App\Enums\BookingStatus;
use App\Enums\RentalOrderStatus;
use App\Exceptions\BusinessException;
use App\Support\DayCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DayCatalogInventoryService
{
    /**
     * Throws when any requested product does not have enough free stock in the period.
     *
     * @param  array<int, array{id: int, quantity: int}>  $items
     */
    public function validateAvailability(
        string $type,
        array $items,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeOrderId = null,
    ): void {
        $modelClass = DayCatalog::modelClass($type);

        foreach ($this->groupItems($items) as $productId => $requested) {
            if ($requested < 1) {
                throw new BusinessException('Quantity must be at least 1.', 'invalid_quantity');
            }

            /** @var Model|null $product */
            $product = $modelClass::query()->find($productId);

            if (! $product || ! $product->is_active) {
                throw new BusinessException(
                    'Item is not available.',
                    'day_catalog_not_available',
                    422,
                    null,
                    [
                        'type' => $type,
                        DayCatalog::productFk($type) => $productId,
                    ],
                );
            }

            $allocated = $this->getAllocatedQuantity($type, $productId, $startAt, $endAt, $excludeOrderId);
            $available = max(0, (int) $product->quantity - $allocated);

            if ($requested > $available) {
                throw new BusinessException(
                    "Not enough \"{$product->name}\" available for the selected period.",
                    'day_catalog_insufficient_quantity',
                    422,
                    null,
                    [
                        'type' => $type,
                        DayCatalog::productFk($type) => $productId,
                        'stock' => (int) $product->quantity,
                        'allocated' => $allocated,
                        'available' => $available,
                        'requested' => $requested,
                    ],
                );
            }
        }
    }

    public function getAllocatedQuantity(
        string $type,
        int $productId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $excludeOrderId = null,
    ): int {
        $itemClass = DayCatalog::orderItemClass($type);
        $table = (new $itemClass)->getTable();
        $fk = DayCatalog::productFk($type);

        return (int) DB::table($table)
            ->join('orders', 'orders.id', '=', "{$table}.order_id")
            ->where("{$table}.{$fk}", $productId)
            ->whereIn('orders.status', $this->blockingStatuses())
            ->where('orders.start_date', '<=', $endAt->copy()->subSecond()->toDateString())
            ->where('orders.end_date', '>=', $startAt->toDateString())
            ->when($excludeOrderId, fn ($q) => $q->where('orders.id', '!=', $excludeOrderId))
            ->sum("{$table}.quantity");
    }

    /**
     * @return array<int, int> product id => free quantity in the period
     */
    public function availableQuantities(string $type, Carbon $startAt, Carbon $endAt): array
    {
        $modelClass = DayCatalog::modelClass($type);

        return $modelClass::query()
            ->where('is_active', true)
            ->get(['id', 'quantity'])
            ->mapWithKeys(fn (Model $product) => [
                $product->id => max(0, (int) $product->quantity - $this->getAllocatedQuantity($type, $product->id, $startAt, $endAt)),
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
            RentalOrderStatus::Pending->value,
            RentalOrderStatus::Confirmed->value,
            RentalOrderStatus::InProgress->value,
        ];
    }

    /**
     * Combined blocking statuses used against the orders table.
     *
     * @return list<string>
     */
    public function blockingStatuses(): array
    {
        return array_values(array_unique(array_merge(
            $this->blockingBookingStatuses(),
            $this->blockingRentalStatuses(),
        )));
    }
}
