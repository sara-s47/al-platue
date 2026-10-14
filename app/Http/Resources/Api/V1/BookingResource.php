<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\PointsTransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'studio_id' => $this->studio_id,
            'package_id' => $this->package_id,
            'booking_mode' => $this->booking_mode,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'guest_count' => $this->guest_count,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'points_discount' => $this->points_discount,
            'points_redeemed' => $this->whenLoaded('pointsTransactions', fn () => (int) abs(
                $this->pointsTransactions
                    ->filter(fn ($tx) => $tx->type === PointsTransactionType::Redemption)
                    ->sum(fn ($tx) => (float) $tx->points)
            )),
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'customer_notes' => $this->customer_notes,
            'studio' => new StudioResource($this->whenLoaded('studio')),
            'customer' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'phone' => $this->user->phone,
                'email' => $this->user->email,
            ] : null),
            'package' => $this->whenLoaded('package', fn () => $this->package ? [
                'id' => $this->package->id,
                'name' => $this->package->name,
                'duration_minutes' => $this->package->duration_minutes,
            ] : null),
            'accessories' => $this->whenLoaded('accessories', fn () => $this->accessories->map(fn ($item) => [
                'id' => $item->id,
                'accessory_id' => $item->accessory_id,
                'name' => $item->accessory?->name,
                'quantity' => $item->quantity,
                'days' => $item->days,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ])->values()),
        ];
    }
}