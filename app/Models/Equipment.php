<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = [
        'name',
        'description',
        'price',
        'quantity',
        'status',
        'is_included',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
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
}
