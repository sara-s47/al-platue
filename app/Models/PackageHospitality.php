<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageHospitality extends Model
{
    protected $table = 'package_hospitality';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'package_id',
        'hospitality_item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function hospitalityItem(): BelongsTo
    {
        return $this->belongsTo(HospitalityItem::class);
    }
}
