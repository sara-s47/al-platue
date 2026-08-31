<?php

namespace App\Models;

use App\Enums\PricingRuleType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'studio_id',
        'rule_type',
        'day_of_week',
        'start_time',
        'end_time',
        'specific_date',
        'price_per_hour',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rule_type' => PricingRuleType::class,
            'day_of_week' => 'integer',
            'specific_date' => 'date',
            'price_per_hour' => 'decimal:2',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }
}
