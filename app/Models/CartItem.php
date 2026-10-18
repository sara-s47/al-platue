<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    public const TYPE_EQUIPMENT = 'equipment';

    public const TYPE_ACCESSORY = 'accessory';

    /** @var list<string> */
    public const TYPES = [
        self::TYPE_EQUIPMENT,
        self::TYPE_ACCESSORY,
    ];

    protected $fillable = [
        'user_id',
        'item_type',
        'item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
