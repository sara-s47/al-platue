<?php

namespace App\Http\Resources\Api\V1;

use App\Support\CatalogCover;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = collect();

        if ($this->relationLoaded('accessoryItems')) {
            $items = $items->merge($this->accessoryItems->map(fn ($item) => [
                'id' => $item->id,
                'item_type' => 'accessory',
                'item_id' => $item->accessory_id,
                'name' => $item->accessory?->name,
                'image' => $item->relationLoaded('accessory')
                    ? CatalogCover::url(
                        $item->accessory?->cover_image,
                        $item->accessory?->relationLoaded('images') ? $item->accessory->images : null,
                    )
                    : null,
                'quantity' => $item->quantity,
                'days' => $item->days,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]));
        }

        if ($this->relationLoaded('equipmentItems')) {
            $items = $items->merge($this->equipmentItems->map(fn ($item) => [
                'id' => $item->id,
                'item_type' => 'equipment',
                'item_id' => $item->equipment_id,
                'name' => $item->equipment?->name,
                'image' => $item->relationLoaded('equipment')
                    ? CatalogCover::url(
                        $item->equipment?->cover_image,
                        $item->equipment?->relationLoaded('images') ? $item->equipment->images : null,
                    )
                    : null,
                'quantity' => $item->quantity,
                'days' => $item->days,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]));
        }

        if ($this->relationLoaded('portableBathroomItems')) {
            $items = $items->merge($this->portableBathroomItems->map(fn ($item) => [
                'id' => $item->id,
                'item_type' => 'portable_bathroom',
                'item_id' => $item->portable_bathroom_id,
                'name' => $item->portableBathroom?->name,
                'image' => $item->relationLoaded('portableBathroom')
                    ? CatalogCover::url(
                        $item->portableBathroom?->cover_image,
                        $item->portableBathroom?->relationLoaded('images') ? $item->portableBathroom->images : null,
                    )
                    : null,
                'quantity' => $item->quantity,
                'days' => $item->days,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]));
        }

        if ($this->relationLoaded('caravanItems')) {
            $items = $items->merge($this->caravanItems->map(fn ($item) => [
                'id' => $item->id,
                'item_type' => 'caravan',
                'item_id' => $item->caravan_id,
                'name' => $item->caravan?->name,
                'image' => $item->relationLoaded('caravan')
                    ? CatalogCover::url(
                        $item->caravan?->cover_image,
                        $item->caravan?->relationLoaded('images') ? $item->caravan->images : null,
                    )
                    : null,
                'quantity' => $item->quantity,
                'days' => $item->days,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]));
        }

        if ($this->relationLoaded('generatorItems')) {
            $items = $items->merge($this->generatorItems->map(fn ($item) => [
                'id' => $item->id,
                'item_type' => 'generator',
                'item_id' => $item->generator_id,
                'name' => $item->generator?->name,
                'image' => $item->relationLoaded('generator')
                    ? CatalogCover::url(
                        $item->generator?->cover_image,
                        $item->generator?->relationLoaded('images') ? $item->generator->images : null,
                    )
                    : null,
                'quantity' => $item->quantity,
                'days' => $item->days,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ]));
        }

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'rental_number' => $this->order_number,
            'booking_id' => $this->booking_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'payment_method' => $this->payment_method instanceof \BackedEnum ? $this->payment_method->value : $this->payment_method,
            'payment_status' => $this->payment_status,
            'is_paid' => $this->is_paid,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => $this->days,
            'subtotal' => $this->subtotal,
            'studio_amount' => $this->studio_amount,
            'paint_surcharge' => $this->paint_surcharge,
            'paint_color' => $this->whenLoaded('paintColor', fn () => $this->paintColor ? [
                'id' => $this->paintColor->id,
                'name' => $this->paintColor->name,
                'hex' => $this->paintColor->hex,
            ] : null),
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'customer_notes' => $this->customer_notes,
            'created_at' => $this->created_at,
            'customer' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ] : null),
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'booking' => $this->whenLoaded('booking', function () {
                if (! $this->booking) {
                    return null;
                }

                // Avoid nesting this order back into the booking payload.
                $this->booking->unsetRelation('order');

                return (new BookingResource($this->booking))->resolve();
            }),
            'items' => $items->values(),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
