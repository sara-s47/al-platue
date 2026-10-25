<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accessory;
use App\Models\Caravan;
use App\Models\CartItem;
use App\Models\Equipment;
use App\Models\Generator;
use App\Models\PortableBathroom;
use App\Models\Studio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;
        $summary = null;

        if ($product instanceof Equipment) {
            $image = $product->relationLoaded('images') && $product->images->isNotEmpty()
                ? $product->images->first()->public_path
                : null;
            $summary = [
                'id' => $product->id,
                'name' => $product->name,
                'price_per_hour' => $product->price,
                'image' => $image,
                'status' => $product->status,
            ];
        } elseif ($product instanceof Accessory
            || $product instanceof PortableBathroom
            || $product instanceof Caravan
            || $product instanceof Generator
        ) {
            $image = $product->relationLoaded('images') && $product->images->isNotEmpty()
                ? $product->images->first()->public_path
                : null;
            $summary = [
                'id' => $product->id,
                'name' => $product->name,
                'price_per_day' => $product->price_per_day,
                'image' => $image,
                'is_active' => $product->is_active,
            ];
        } elseif ($product instanceof Studio) {
            $image = null;
            if ($product->relationLoaded('images') && $product->images->isNotEmpty()) {
                $first = $product->images->sortBy('sort_order')->first();
                $image = $first->public_path
                    ?? \App\Support\PublicStorageUrl::from($first->path);
            }
            $summary = [
                'id' => $product->id,
                'name' => $product->name,
                'image' => $image,
                'paint_pricing_mode' => $product->paint_pricing_mode ?? 'flat_extra',
                'flat_extra_paint_price' => $product->flat_extra_paint_price,
                'paint_colors' => $product->relationLoaded('paintColors')
                    ? StudioPaintColorResource::collection($product->paintColors)
                    : null,
            ];
        }

        return [
            'id' => $this->id,
            'type' => $this->item_type,
            'item_id' => $this->item_id,
            'quantity' => $this->quantity,
            'meta' => $this->meta,
            'item' => $summary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
