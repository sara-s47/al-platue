<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BookingEquipment;
use App\Models\EquipmentRentalItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of an equipment's rental history (standalone rental item or booking allocation).
 */
class EquipmentRentalHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof BookingEquipment) {
            $booking = $this->booking;

            return [
                'id' => $this->id,
                'type' => 'with_studio',
                'booking_id' => $booking?->id,
                'booking_number' => $booking?->booking_number,
                'rental_id' => null,
                'rental_number' => null,
                'studio' => $booking?->studio ? [
                    'id' => $booking->studio->id,
                    'name' => $booking->studio->name,
                ] : null,
                'customer' => $booking?->user ? [
                    'id' => $booking->user->id,
                    'name' => $booking->user->name,
                ] : null,
                'start' => $booking ? Carbon::parse($booking->start_at)->toIso8601String() : null,
                'end' => $booking ? Carbon::parse($booking->end_at)->toIso8601String() : null,
                'days' => null,
                'quantity' => $this->quantity,
                'unit_price' => $this->unit_price,
                'total_price' => $this->total_price,
                'status' => $booking?->status,
                'created_at' => $this->created_at,
            ];
        }

        /** @var EquipmentRentalItem $item */
        $item = $this->resource;
        $rental = $item->rental;

        return [
            'id' => $item->id,
            'type' => 'standalone',
            'booking_id' => null,
            'booking_number' => null,
            'rental_id' => $rental?->id,
            'rental_number' => $rental?->rental_number,
            'studio' => null,
            'customer' => $rental?->user ? [
                'id' => $rental->user->id,
                'name' => $rental->user->name,
            ] : null,
            'start' => $rental?->start_date?->toDateString(),
            'end' => $rental?->end_date?->toDateString(),
            'days' => $item->days,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_price' => $item->total_price,
            'status' => $rental?->status,
            'created_at' => $item->created_at,
        ];
    }
}
