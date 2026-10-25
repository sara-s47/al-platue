<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudioPaintColor extends Model
{
    protected $fillable = [
        'studio_id',
        'name',
        'hex',
        'is_default',
        'price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'paint_color_id');
    }
}
