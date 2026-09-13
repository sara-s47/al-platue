<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $images = $this->whenLoaded('images', function () {
            return $this->images->map(fn ($image) => [
                'id' => $image->id,
                'path' => $image->path,
                'url' => $image->public_path,
            ])->values();
        });

        $primary = null;
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $primary = $this->images->first()->public_path;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_per_hour' => $this->price ?? null,
            'quantity' => $this->quantity ?? $this->studio_quantity ?? null,
            'status' => $this->status ?? null,
            'image' => $primary,
            'images' => $images,
        ];
    }
}
