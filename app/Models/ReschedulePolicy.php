<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReschedulePolicy extends Model
{
    protected $fillable = [
        'name',
        'min_notice_hours',
        'max_reschedules',
        'fee',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_notice_hours' => 'integer',
            'max_reschedules' => 'integer',
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
