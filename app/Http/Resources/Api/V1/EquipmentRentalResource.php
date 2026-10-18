<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentRentalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rental_number' => $this->rental_number,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
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
                'equipment_id' => $item->equipment_id,
                'name' => $item->equipment?->name,
                'image' => $item->relationLoaded('equipment') && $item->equipment?->relationLoaded('images')
                    ? $item->equipment->images->first()?->public_path
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
