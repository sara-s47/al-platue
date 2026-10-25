<?php

namespace App\Http\Resources\Api\V1;

use App\Support\CatalogCover;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cover = PublicStorageUrl::from($this->cover_image);
        $primary = CatalogCover::url(
            $this->cover_image,
            $this->relationLoaded('images') ? $this->images : null,
            sortBySortOrder: true,
        );

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
            'cover_image' => $cover,
            'image' => $primary,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => StudioImageResource::collection($this->whenLoaded('images')),
            'paint_colors' => StudioPaintColorResource::collection($this->whenLoaded('paintColors')),
        ];
    }
}
