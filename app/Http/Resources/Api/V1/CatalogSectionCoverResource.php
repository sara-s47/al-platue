<?php

namespace App\Http\Resources\Api\V1;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogSectionCoverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $section = $this->section instanceof \BackedEnum ? $this->section->value : $this->section;

        return [
            'section' => $section,
            'cover_image' => PublicStorageUrl::from($this->cover_image),
        ];
    }
}
