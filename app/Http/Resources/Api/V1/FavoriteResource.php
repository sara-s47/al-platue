<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accessory;
use App\Models\Equipment;
use App\Models\Favorite;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->favoritable_type;
        $item = null;

        if ($this->relationLoaded('favoritable') && $this->favoritable) {
            $item = match (true) {
                $this->favoritable instanceof Studio => (new StudioResource($this->favoritable))->resolve(),
                $this->favoritable instanceof Equipment => (new EquipmentResource(
                    $this->favoritable->relationLoaded('images')
                        ? $this->favoritable
                        : $this->favoritable->load('images')
                ))->resolve(),
                $this->favoritable instanceof Accessory => (new AccessoryResource(
                    $this->favoritable->relationLoaded('images')
                        ? $this->favoritable
                        : $this->favoritable->load('images')
                ))->resolve(),
                default => null,
            };
        }

        return [
            'id' => $this->id,
            'type' => $type,
            'item_id' => $this->favoritable_id,
            // Backward-compatible field for studio favorites.
            'studio_id' => $type === Favorite::TYPE_STUDIO ? $this->favoritable_id : null,
            'item' => $item,
            'created_at' => $this->created_at,
        ];
    }
}
