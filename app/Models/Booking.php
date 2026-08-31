<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'booking_number',
        'user_id',
        'studio_id',
        'package_id',
        'start_at',
        'end_at',
        'guest_count',
        'status',
        'subtotal',
        'discount_amount',
        'points_discount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'customer_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'guest_count' => 'integer',
            'status' => BookingStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'points_discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(BookingEquipment::class);
    }

    public function hospitality(): HasMany
    {
        return $this->hasMany(BookingHospitality::class);
    }

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(BookingPriceSnapshot::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(BookingChange::class);
    }

    public function internalNotes(): HasMany
    {
        return $this->hasMany(BookingInternalNote::class);
    }

    public function overtimeRecords(): HasMany
    {
        return $this->hasMany(OvertimeRecord::class);
    }

    public function pointsTransactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class);
    }

    public function promoCodeUsages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
