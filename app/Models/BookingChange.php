<?php

namespace App\Models;

use App\Enums\BookingChangeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'booking_id',
        'type',
        'old_start_at',
        'old_end_at',
        'new_start_at',
        'new_end_at',
        'fee',
        'price_difference',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => BookingChangeType::class,
            'old_start_at' => 'datetime',
            'old_end_at' => 'datetime',
            'new_start_at' => 'datetime',
            'new_end_at' => 'datetime',
            'fee' => 'decimal:2',
            'price_difference' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
