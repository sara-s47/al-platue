<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaravanImage extends Model
{
    protected $fillable = [
        'caravan_id',
        'path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function caravan(): BelongsTo
    {
        return $this->belongsTo(Caravan::class);
    }

    public function getPublicPathAttribute(): ?string
    {
        return PublicStorageUrl::from($this->path);
    }
}
