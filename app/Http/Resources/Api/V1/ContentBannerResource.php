<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentBannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'image_path' => $this->image_path,
            'link_url' => $this->link_url,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}