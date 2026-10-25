<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of a day-catalog product's order history.
 */
class DayCatalogOrderHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $customer = $order?->user;

        return [
            'id' => $this->id,
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
            ] : null,
            'start' => $order?->start_date?->toDateString(),
            'end' => $order?->end_date?->toDateString(),
            'days' => $this->days,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'status' => $this->statusValue($order?->status),
            'created_at' => $this->created_at,
        ];
    }

    protected function statusValue(mixed $status): mixed
    {
        return $status instanceof \BackedEnum ? $status->value : $status;
    }
}
