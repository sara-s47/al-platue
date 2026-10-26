<?php

namespace App\Http\Resources\Api\V1;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InteriorDesignerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'phone' => $this->phone,
            'image' => PublicStorageUrl::from($this->image_path),
            'portfolio_url' => $this->portfolio_url,
            'notes' => $this->notes ?? [],
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
