<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewDimension extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scores(): HasMany
    {
        return $this->hasMany(ReviewDimensionScore::class, 'dimension_id');
    }

    public function reviews(): BelongsToMany
    {
        return $this->belongsToMany(Review::class, 'review_dimension_scores', 'dimension_id', 'review_id')
            ->withPivot('score');
    }
}
