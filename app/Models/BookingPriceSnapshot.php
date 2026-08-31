<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookingPriceSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'booking_id',
        'item_type',
        'item_id',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function item(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'item_type', 'item_id');
    }
}
