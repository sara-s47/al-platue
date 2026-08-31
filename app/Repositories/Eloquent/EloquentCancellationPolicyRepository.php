<?php

namespace App\Repositories\Eloquent;

use App\Models\CancellationPolicy;
use App\Repositories\Contracts\CancellationPolicyRepositoryInterface;

class EloquentCancellationPolicyRepository extends BaseRepository implements CancellationPolicyRepositoryInterface
{
    public function __construct(CancellationPolicy $model)
    {
        parent::__construct($model);
    }
}
