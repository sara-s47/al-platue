<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HospitalityItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $studiosLoaded = $this->resource instanceof Model && $this->resource->relationLoaded('studios');

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description,
            'pricing_model' => $this->pricing_model,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'is_active' => $this->is_active,
            'category_name' => $this->category_name ?? null,
            'available_quantity' => $this->when(
                isset($this->resource->available_quantity),
                fn () => (int) $this->resource->available_quantity,
            ),
            'available_in_all_studios' => $this->when($studiosLoaded, fn () => $this->resource->studios->isEmpty()),
            'studios' => $this->when($studiosLoaded, fn () => $this->resource->studios
                ->map(fn ($studio) => ['id' => $studio->id, 'name' => $studio->name])
                ->values()),
        ];
    }
}
