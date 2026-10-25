<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortableBathroomImage extends Model
{
    protected $fillable = [
        'portable_bathroom_id',
        'path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function portableBathroom(): BelongsTo
    {
        return $this->belongsTo(PortableBathroom::class);
    }

    public function getPublicPathAttribute(): ?string
    {
        return PublicStorageUrl::from($this->path);
    }
}
