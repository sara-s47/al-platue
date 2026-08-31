<?php

namespace App\Models;

use App\Enums\HospitalityPricingModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HospitalityItem extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'pricing_model',
        'price',
        'quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pricing_model' => HospitalityPricingModel::class,
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HospitalityCategory::class, 'category_id');
    }

    public function studios(): BelongsToMany
    {
        return $this->belongsToMany(Studio::class, 'studio_hospitality');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_hospitality')
            ->withPivot('quantity');
    }

    public function bookingHospitality(): HasMany
    {
        return $this->hasMany(BookingHospitality::class);
    }
}
