<?php

namespace App\Repositories\Eloquent;

use App\Models\ReschedulePolicy;
use App\Repositories\Contracts\ReschedulePolicyRepositoryInterface;

class EloquentReschedulePolicyRepository extends BaseRepository implements ReschedulePolicyRepositoryInterface
{
    public function __construct(ReschedulePolicy $model)
    {
        parent::__construct($model);
    }
}
