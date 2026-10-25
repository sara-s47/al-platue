<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Studio extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'capacity',
        'address',
        'latitude',
        'longitude',
        'rules',
        'is_active',
        'sort_order',
        'paint_pricing_mode',
        'flat_extra_paint_price',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'flat_extra_paint_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(StudioImage::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(StudioSchedule::class);
    }

    public function scheduleOverrides(): HasMany
    {
        return $this->hasMany(StudioScheduleOverride::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(StudioBlock::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    public function bookingHolds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function favorites(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudioItem::class);
    }

    public function paintColors(): HasMany
    {
        return $this->hasMany(StudioPaintColor::class)->orderBy('sort_order')->orderBy('id');
    }

    public function savedSetups(): HasMany
    {
        return $this->hasMany(SavedSetup::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'studio_equipment')
            ->withPivot('quantity');
    }

    public function hospitalityItems(): BelongsToMany
    {
        return $this->belongsToMany(HospitalityItem::class, 'studio_hospitality');
    }

    public function promoCodes(): BelongsToMany
    {
        return $this->belongsToMany(PromoCode::class, 'promo_code_studios');
    }
}
