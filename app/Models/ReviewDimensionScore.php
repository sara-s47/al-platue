<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewDimensionScore extends Model
{
    protected $table = 'review_dimension_scores';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'review_id',
        'dimension_id',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(ReviewDimension::class, 'dimension_id');
    }
}
