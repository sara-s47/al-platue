<?php

namespace App\Repositories\Eloquent;

use App\Models\Refund;
use App\Repositories\Contracts\RefundRepositoryInterface;

class EloquentRefundRepository extends BaseRepository implements RefundRepositoryInterface
{
    public function __construct(Refund $model)
    {
        parent::__construct($model);
    }
}
