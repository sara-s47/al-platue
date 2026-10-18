<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Favorite extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_STUDIO = 'studio';

    public const TYPE_EQUIPMENT = 'equipment';

    public const TYPE_ACCESSORY = 'accessory';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_STUDIO,
        self::TYPE_EQUIPMENT,
        self::TYPE_ACCESSORY,
    ];

    protected $fillable = [
        'user_id',
        'favoritable_type',
        'favoritable_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function favoritable(): MorphTo
    {
        return $this->morphTo();
    }
}
