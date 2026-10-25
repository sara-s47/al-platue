<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    public const TYPE_EQUIPMENT = 'equipment';

    public const TYPE_ACCESSORY = 'accessory';

    public const TYPE_STUDIO = 'studio';

    public const TYPE_PORTABLE_BATHROOM = 'portable_bathroom';

    public const TYPE_CARAVAN = 'caravan';

    public const TYPE_GENERATOR = 'generator';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_EQUIPMENT,
        self::TYPE_ACCESSORY,
        self::TYPE_STUDIO,
        self::TYPE_PORTABLE_BATHROOM,
        self::TYPE_CARAVAN,
        self::TYPE_GENERATOR,
    ];

    /** @var list<string> */
    public const DAY_CATALOG_TYPES = [
        self::TYPE_PORTABLE_BATHROOM,
        self::TYPE_CARAVAN,
        self::TYPE_GENERATOR,
    ];

    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'quantity',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
