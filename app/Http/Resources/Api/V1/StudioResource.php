<?php

namespace App\Http\Resources\Api\V1;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primary = null;
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $primary = PublicStorageUrl::from($this->images->sortBy('sort_order')->first()->path);
        }

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'rules' => $this->rules,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'paint_pricing_mode' => $this->paint_pricing_mode ?? 'flat_extra',
            'flat_extra_paint_price' => $this->flat_extra_paint_price,
            'image' => $primary,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => StudioImageResource::collection($this->whenLoaded('images')),
            'paint_colors' => StudioPaintColorResource::collection($this->whenLoaded('paintColors')),
        ];
    }
}