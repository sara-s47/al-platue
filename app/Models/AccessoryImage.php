<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessoryImage extends Model
{
    protected $fillable = [
        'accessory_id',
        'path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Accessory::class);
    }

    public function getPublicPathAttribute(): ?string
    {
        return PublicStorageUrl::from($this->path);
    }
}
