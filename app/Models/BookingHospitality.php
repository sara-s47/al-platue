<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingHospitality extends Model
{
    protected $table = 'booking_hospitality';

    protected $fillable = [
        'booking_id',
        'hospitality_item_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function hospitalityItem(): BelongsTo
    {
        return $this->belongsTo(HospitalityItem::class);
    }
}
