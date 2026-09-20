<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studio_id' => $this->studio_id,
            'name' => $this->name,
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
            'price' => $this->price,
            'is_active' => $this->is_active,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'equipment' => $this->whenLoaded('equipment', function () {
                return $this->equipment->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => (int) $item->pivot->quantity,
                    'price' => $item->price,
                ])->values();
            }),
            'hospitality' => $this->whenLoaded('hospitalityItems', function () {
                return $this->hospitalityItems->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => (int) $item->pivot->quantity,
                    'price' => $item->price,
                ])->values();
            }),
        ];
    }
}
