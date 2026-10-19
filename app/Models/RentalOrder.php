<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RentalOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalOrder extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'start_date',
        'end_date',
        'days',
        'status',
        'payment_method',
        'subtotal',
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

    public function accessoryItems(): HasMany
    {
        return $this->hasMany(AccessoryRentalItem::class, 'rental_order_id');
    }

    public function equipmentItems(): HasMany
    {
        return $this->hasMany(EquipmentRentalItem::class, 'rental_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
