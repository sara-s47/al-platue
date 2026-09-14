<?php

namespace App\Http\Resources\Api\V1;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudioImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'path' => PublicStorageUrl::from($this->path),
            'alt_text' => $this->alt_text,
            'sort_order' => $this->sort_order,
        ];
    }
}