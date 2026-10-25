<?php

namespace App\Http\Resources\Api\V1;

use App\Support\CatalogCover;
use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->whenLoaded('images', function () {
            return $this->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->public_path,
                'url' => $image->public_path,
            ])->values();
        });

        $cover = PublicStorageUrl::from($this->cover_image);
        $primary = CatalogCover::url(
            $this->cover_image,
            $this->relationLoaded('images') ? $this->images : null,
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_per_hour' => $this->price ?? null,
            'price_per_day' => $this->price_per_day ?? $this->price ?? null,
            'quantity' => $this->quantity ?? $this->studio_quantity ?? null,
            'status' => $this->status ?? null,
            'cover_image' => $cover,
            'image' => $primary,
            'images' => $images,
            'available_quantity' => $this->when(
                array_key_exists('available_quantity', $this->resource->getAttributes()),
                fn () => (int) $this->available_quantity,
            ),
            'available_in_all_studios' => $this->whenLoaded('studios', fn () => $this->studios->isEmpty()),
            'studios' => $this->whenLoaded('studios', function () {
                return $this->studios->map(fn ($studio) => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                    'quantity' => (int) $studio->pivot->quantity,
                ])->values();
            }),
        ];
    }
}
