<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'studio_id' => $this->studio_id,
            'package_id' => $this->package_id,
            'booking_mode' => $this->booking_mode,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'guest_count' => $this->guest_count,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'points_discount' => $this->points_discount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'customer_notes' => $this->customer_notes,
            'studio' => new StudioResource($this->whenLoaded('studio')),
        ];
    }
}