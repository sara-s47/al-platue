<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedSetupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'studio_id' => $this->studio_id,
            'duration_minutes' => $this->duration_minutes,
            'guest_count' => $this->guest_count,
            'configuration_json' => $this->configuration_json,
        ];
    }
}