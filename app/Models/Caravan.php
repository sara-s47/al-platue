<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Caravan extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price_per_day',
        'quantity',
        'is_active',
        'cover_image',
    ];

    protected function casts(): array
    {
        return [
            'price_per_day' => 'decimal:2',
            'quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(CaravanImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(CaravanOrderItem::class);
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }
}
