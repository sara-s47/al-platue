<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Accessory extends Model
{
    protected $fillable = [
        'category_id',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(AccessoryCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(AccessoryImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rentalItems(): HasMany
    {
        return $this->hasMany(AccessoryRentalItem::class);
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }
}
