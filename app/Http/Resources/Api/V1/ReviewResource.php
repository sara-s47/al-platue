<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'studio_id' => $this->studio_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'status' => $this->status,
            'edited_at' => $this->edited_at,
        ];
    }
}