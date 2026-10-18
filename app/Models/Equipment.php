<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = [
        'name',
        'description',
        'price',
        'price_per_day',
        'quantity',
        'status',
        'is_included',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_per_day' => 'decimal:2',
            'quantity' => 'integer',
            'status' => EquipmentStatus::class,
            'is_included' => 'boolean',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(EquipmentImage::class);
    }

    public function studios(): BelongsToMany
    {
        return $this->belongsToMany(Studio::class, 'studio_equipment')
            ->withPivot('quantity');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_equipment')
            ->withPivot('quantity');
    }

    public function bookingEquipment(): HasMany
    {
        return $this->hasMany(BookingEquipment::class);
    }

    public function rentalItems(): HasMany
    {
        return $this->hasMany(EquipmentRentalItem::class);
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }
}
