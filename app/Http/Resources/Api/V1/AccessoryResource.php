<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccessoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
            'id' => $image->id,
            'path' => $image->public_path,
            'sort_order' => $image->sort_order,
        ])->values());

        $primary = null;
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $primary = $this->images->first()->public_path;
        }

        $stats = $this->resource->getAttribute('stats');
        $available = $this->resource->getAttribute('available_quantity');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_per_day' => $this->price_per_day,
            'quantity' => $this->quantity,
            'is_active' => $this->is_active,
            'available_quantity' => $this->when($available !== null, $available),
            'image' => $primary,
            'images' => $images,
            'stats' => $this->when($stats !== null, $stats),
        ];
    }
}
