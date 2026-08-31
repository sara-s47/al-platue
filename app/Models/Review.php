<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    protected $fillable = [
        'booking_id',
        'user_id',
        'studio_id',
        'rating',
        'comment',
        'status',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'status' => ReviewStatus::class,
            'edited_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function dimensionScores(): HasMany
    {
        return $this->hasMany(ReviewDimensionScore::class);
    }

    public function dimensions(): BelongsToMany
    {
        return $this->belongsToMany(ReviewDimension::class, 'review_dimension_scores', 'review_id', 'dimension_id')
            ->withPivot('score');
    }
}
