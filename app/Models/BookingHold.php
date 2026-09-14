<?php

namespace App\Models;

use App\Enums\BookingHoldStatus;
use App\Enums\BookingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingHold extends Model
{
    protected $fillable = [
        'user_id',
        'studio_id',
        'booking_mode',
        'start_at',
        'end_at',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'booking_mode' => BookingMode::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => BookingHoldStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }
}
