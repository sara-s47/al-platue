<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accessory;
use App\Models\Caravan;
use App\Models\Equipment;
use App\Models\Generator;
use App\Models\PortableBathroom;
use App\Models\Studio;
use App\Services\Pricing\PricingService;
use App\Support\CatalogCover;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->relationLoaded('product') ? $this->product : null;
        $summary = null;

        if ($product instanceof Equipment) {
            $summary = [
                'id' => $product->id,
                'name' => $product->name,
                'price_per_hour' => $product->price,
                'image' => CatalogCover::url(
                    $product->cover_image,
                    $product->relationLoaded('images') ? $product->images : null,
                ),
                'status' => $product->status,
            ];
        } elseif ($product instanceof Accessory
            || $product instanceof PortableBathroom
            || $product instanceof Caravan
            || $product instanceof Generator
        ) {
            $summary = [
                'id' => $product->id,
                'name' => $product->name,
                'price_per_day' => $product->price_per_day,
                'image' => CatalogCover::url(
                    $product->cover_image,
                    $product->relationLoaded('images') ? $product->images : null,
                ),
                'is_active' => $product->is_active,
            ];
        } elseif ($product instanceof Studio) {
            $rates = app(PricingService::class)->displayRatesForStudio($product);
            $summary = [
                'id' => $product->id,
                'name' => $product->name,
                'image' => CatalogCover::url(
                    $product->cover_image,
                    $product->relationLoaded('images') ? $product->images : null,
                    sortBySortOrder: true,
                ),
                'price_per_hour' => $rates['price_per_hour'],
                'price_per_day' => $rates['price_per_day'],
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
