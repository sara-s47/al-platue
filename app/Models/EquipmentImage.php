<?php

namespace App\Models;

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
     * Path relative to the project root: storage/app/public/{rel_path}
     * No storage:link required — frontend prepends the API base URL.
     */
    public function getPublicPathAttribute(): string
    {
        return 'storage/app/public/'.ltrim((string) $this->path, '/');
    }
}
