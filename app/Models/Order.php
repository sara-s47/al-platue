<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RentalOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'user_id',
        'booking_id',
        'start_date',
        'end_date',
        'days',
        'status',
        'payment_method',
        'subtotal',
        'studio_amount',
        'paint_surcharge',
        'paint_color_id',
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
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'days' => 'integer',
            'status' => RentalOrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'decimal:2',
            'studio_amount' => 'decimal:2',
            'paint_surcharge' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function paintColor(): BelongsTo
    {
        return $this->belongsTo(StudioPaintColor::class, 'paint_color_id');
    }

    public function accessoryItems(): HasMany
    {
        return $this->hasMany(AccessoryRentalItem::class, 'order_id');
    }

    public function equipmentItems(): HasMany
    {
        return $this->hasMany(EquipmentRentalItem::class, 'order_id');
    }

    public function portableBathroomItems(): HasMany
    {
        return $this->hasMany(PortableBathroomOrderItem::class, 'order_id');
    }

    public function caravanItems(): HasMany
    {
        return $this->hasMany(CaravanOrderItem::class, 'order_id');
    }

    public function generatorItems(): HasMany
    {
        return $this->hasMany(GeneratorOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function getIsPaidAttribute(): bool
    {
        return (float) $this->remaining_amount <= 0;
    }

    public function getPaymentStatusAttribute(): string
    {
        $remaining = (float) $this->remaining_amount;
        $paid = (float) $this->paid_amount;

        if ($remaining <= 0) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        return 'unpaid';
    }
}
