<?php

namespace App\Http\Resources\Api\V1;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudioItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studio_id' => $this->studio_id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'image' => PublicStorageUrl::from($this->image_path),
            'colors' => $this->colors ?? [],
            'default_quantity' => $this->default_quantity,
            'min_quantity' => $this->min_quantity,
            'is_active' => $this->is_active,
        ];
    }
}
