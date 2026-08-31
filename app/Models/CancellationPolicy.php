<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CancellationPolicy extends Model
{
    protected $fillable = [
        'name',
        'hours_before',
        'refund_percentage',
        'cancellation_fee',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hours_before' => 'integer',
            'refund_percentage' => 'decimal:2',
            'cancellation_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
