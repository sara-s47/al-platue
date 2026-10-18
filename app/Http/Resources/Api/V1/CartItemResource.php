<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accessory;
use App\Models\CartItem;
use App\Models\Equipment;
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
        } elseif ($product instanceof Accessory) {
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
        }

        return [
            'id' => $this->id,
            'type' => $this->item_type,
            'item_id' => $this->item_id,
            'quantity' => $this->quantity,
            'item' => $summary,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
