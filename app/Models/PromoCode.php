<?php

namespace App\Models;

use App\Enums\PromoCodeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'minimum_booking_value',
        'usage_limit',
        'per_user_limit',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromoCodeType::class,
            'value' => 'decimal:2',
            'minimum_booking_value' => 'decimal:2',
            'usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function studios(): BelongsToMany
    {
        return $this->belongsToMany(Studio::class, 'promo_code_studios');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }
}
