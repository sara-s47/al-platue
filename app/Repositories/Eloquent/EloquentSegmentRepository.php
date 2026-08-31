<?php

namespace App\Repositories\Eloquent;

use App\Models\Segment;
use App\Repositories\Contracts\SegmentRepositoryInterface;

class EloquentSegmentRepository extends BaseRepository implements SegmentRepositoryInterface
{
    public function __construct(Segment $model)
    {
        parent::__construct($model);
    }
}
