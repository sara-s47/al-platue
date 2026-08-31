<?php

namespace App\Repositories\Eloquent;

use App\Models\HospitalityItem;
use App\Repositories\Contracts\HospitalityItemRepositoryInterface;

class EloquentHospitalityItemRepository extends BaseRepository implements HospitalityItemRepositoryInterface
{
    public function __construct(HospitalityItem $model)
    {
        parent::__construct($model);
    }
}
