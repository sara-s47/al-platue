<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudioPaintColorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studio_id' => $this->studio_id,
            'name' => $this->name,
            'hex' => $this->hex,
            'is_default' => $this->is_default,
            'price' => $this->price,
            'sort_order' => $this->sort_order,
        ];
    }
}
