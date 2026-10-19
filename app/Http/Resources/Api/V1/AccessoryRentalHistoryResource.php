<?php

namespace App\Http\Resources\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of an accessory's rental history (an accessory_rental_items record).
 */
class AccessoryRentalHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->booking;
        $order = $this->rentalOrder;
        $customer = $booking?->user ?? $order?->user;

        return [
            'id' => $this->id,
            'type' => $booking ? 'with_studio' : 'standalone',
            'booking_id' => $booking?->id,
            'booking_number' => $booking?->booking_number,
            'order_id' => $order?->id,
            'order_number' => $order?->order_number,
            'rental_id' => $order?->id,
            'rental_number' => $order?->order_number,
            'studio' => $this->studio ? [
                'id' => $this->studio->id,
                'name' => $this->studio->name,
            ] : null,
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
            ] : null,
            'start' => $booking
                ? Carbon::parse($booking->start_at)->toIso8601String()
                : $order?->start_date?->toDateString(),
            'end' => $booking
                ? Carbon::parse($booking->end_at)->toIso8601String()
                : $order?->end_date?->toDateString(),
            'days' => $this->days,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'status' => $this->statusValue($booking?->status ?? $order?->status),
            'created_at' => $this->created_at,
        ];
    }

    protected function statusValue(mixed $status): mixed
    {
        return $status instanceof \BackedEnum ? $status->value : $status;
    }
}
