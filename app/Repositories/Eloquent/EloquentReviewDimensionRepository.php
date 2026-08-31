<?php

namespace App\Repositories\Eloquent;

use App\Models\ReviewDimension;
use App\Repositories\Contracts\ReviewDimensionRepositoryInterface;

class EloquentReviewDimensionRepository extends BaseRepository implements ReviewDimensionRepositoryInterface
{
    public function __construct(ReviewDimension $model)
    {
        parent::__construct($model);
    }
}
