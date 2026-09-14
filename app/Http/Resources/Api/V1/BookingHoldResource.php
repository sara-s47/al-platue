<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingHoldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studio_id' => $this->studio_id,
            'booking_mode' => $this->booking_mode,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'expires_at' => $this->expires_at,
            'status' => $this->status,
        ];
    }
}