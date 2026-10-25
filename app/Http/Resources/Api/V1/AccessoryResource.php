<?php

namespace App\Http\Resources\Api\V1;

use App\Support\CatalogCover;
use App\Support\PublicStorageUrl;
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

        $cover = PublicStorageUrl::from($this->cover_image);
        $primary = CatalogCover::url(
            $this->cover_image,
            $this->relationLoaded('images') ? $this->images : null,
        );

        $stats = $this->resource->getAttribute('stats');
        $available = $this->resource->getAttribute('available_quantity');

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description,
            'price_per_day' => $this->price_per_day,
            'quantity' => $this->quantity,
            'is_active' => $this->is_active,
            'available_quantity' => $this->when($available !== null, $available),
            'cover_image' => $cover,
            'image' => $primary,
            'images' => $images,
            'category' => new AccessoryCategoryResource($this->whenLoaded('category')),
            'stats' => $this->when($stats !== null, $stats),
        ];
    }
}
