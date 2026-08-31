<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyCampaign extends Model
{
    protected $fillable = [
        'name',
        'points',
        'reason',
        'expires_at',
        'status',
        'scheduled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
            'expires_at' => 'datetime',
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): HasMany
    {
        return $this->hasMany(LoyaltyCampaignUser::class, 'campaign_id');
    }

    public function pointsTransactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class, 'campaign_id');
    }
}
