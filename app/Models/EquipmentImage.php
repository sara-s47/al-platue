<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentImage extends Model
{
    protected $fillable = [
        'equipment_id',
        'path',
    ];

    protected $appends = [
        'public_path',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Public URL: {baseUrl}/storage/app/public/{rel_path}
     */
    public function getPublicPathAttribute(): ?string
    {
        return PublicStorageUrl::from($this->path);
    }
}
