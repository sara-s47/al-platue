<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studio_id' => $this->studio_id,
            'studio' => new StudioResource($this->whenLoaded('studio')),
            'created_at' => $this->created_at,
        ];
    }
}