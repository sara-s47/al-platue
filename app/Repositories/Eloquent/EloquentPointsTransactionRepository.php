<?php

namespace App\Repositories\Eloquent;

use App\Models\PointsTransaction;
use App\Repositories\Contracts\PointsTransactionRepositoryInterface;

class EloquentPointsTransactionRepository extends BaseRepository implements PointsTransactionRepositoryInterface
{
    public function __construct(PointsTransaction $model)
    {
        parent::__construct($model);
    }
}
