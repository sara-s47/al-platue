<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessoryRentalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rental_number' => $this->rental_number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'payment_method' => $this->payment_method instanceof \BackedEnum ? $this->payment_method->value : $this->payment_method,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => $this->days,
            'subtotal' => $this->subtotal,
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
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'accessory_id' => $item->accessory_id,
                'name' => $item->accessory?->name,
                'image' => $item->relationLoaded('accessory')
                    ? \App\Support\CatalogCover::url(
                        $item->accessory?->cover_image,
                        $item->accessory?->relationLoaded('images') ? $item->accessory->images : null,
                    )
                    : null,
                'quantity' => $item->quantity,
                'days' => $item->days,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ])->values()),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
