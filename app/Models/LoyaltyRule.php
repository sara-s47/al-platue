<?php

namespace App\Models;

use App\Enums\LoyaltyRuleType;
use Illuminate\Database\Eloquent\Model;

class LoyaltyRule extends Model
{
    protected $fillable = [
        'name',
        'rule_type',
        'value',
        'points',
        'min_redemption_points',
        'max_redemption_amount',
        'max_redemption_percentage',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rule_type' => LoyaltyRuleType::class,
            'value' => 'decimal:2',
            'points' => 'decimal:2',
            'min_redemption_points' => 'integer',
            'max_redemption_amount' => 'decimal:2',
            'max_redemption_percentage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
