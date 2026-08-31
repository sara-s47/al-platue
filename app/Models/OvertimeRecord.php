<?php

namespace App\Models;

use App\Enums\OvertimeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRecord extends Model
{
    protected $fillable = [
        'booking_id',
        'grace_minutes',
        'overtime_minutes',
        'interval_minutes',
        'rate',
        'amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'interval_minutes' => 'integer',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'status' => OvertimeStatus::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
