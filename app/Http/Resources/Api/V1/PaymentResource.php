<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'accessory_rental_id' => $this->accessory_rental_id,
            'equipment_rental_id' => $this->equipment_rental_id,
            'rental_order_id' => $this->order_id ?? $this->rental_order_id,
            'order_id' => $this->order_id ?? $this->rental_order_id,
            'type' => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'amount' => $this->amount,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'paid_at' => $this->paid_at,
        ];
    }
}