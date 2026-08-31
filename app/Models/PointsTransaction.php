<?php

namespace App\Models;

use App\Enums\PointsTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointsTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'booking_id',
        'campaign_id',
        'type',
        'points',
        'reference_type',
        'reference_id',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => PointsTransactionType::class,
            'points' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCampaign::class, 'campaign_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }
}
