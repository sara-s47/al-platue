<?php

namespace App\Models;

use App\Enums\CampaignUserStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyCampaignUser extends Model
{
    protected $fillable = [
        'campaign_id',
        'user_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => CampaignUserStatus::class,
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
