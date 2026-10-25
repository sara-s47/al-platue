<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessoryRentalItem extends Model
{
    protected $fillable = [
        'accessory_id',
        'booking_id',
        'accessory_rental_id',
        'rental_order_id',
        'order_id',
        'studio_id',
        'quantity',
        'days',
        'unit_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'days' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(AccessoryRental::class, 'accessory_rental_id');
    }

    public function rentalOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }
}
