<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentRentalItem extends Model
{
    protected $fillable = [
        'equipment_id',
        'equipment_rental_id',
        'quantity',
        'days',
        'unit_price',
        'total_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'days' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(EquipmentRental::class, 'equipment_rental_id');
    }
}
