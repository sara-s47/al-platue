<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSetup extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'studio_id',
        'duration_minutes',
        'guest_count',
        'configuration_json',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'guest_count' => 'integer',
            'configuration_json' => 'array',
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
}
