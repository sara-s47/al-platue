<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudioItem extends Model
{
    protected $fillable = [
        'studio_id',
        'name',
        'description',
        'price',
        'image_path',
        'colors',
        'default_quantity',
        'min_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'colors' => 'array',
            'default_quantity' => 'integer',
            'min_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function bookingStudioItems(): HasMany
    {
        return $this->hasMany(BookingStudioItem::class);
    }
}
