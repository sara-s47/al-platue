<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStudioItem extends Model
{
    protected $fillable = [
        'booking_id',
        'studio_item_id',
        'quantity',
        'default_quantity',
        'min_quantity',
        'unit_price',
        'deduction_amount',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'default_quantity' => 'integer',
            'min_quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function studioItem(): BelongsTo
    {
        return $this->belongsTo(StudioItem::class);
    }
}
