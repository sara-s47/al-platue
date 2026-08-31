<?php

namespace App\Repositories\Eloquent;

use App\Models\HospitalityCategory;
use App\Repositories\Contracts\HospitalityCategoryRepositoryInterface;

class EloquentHospitalityCategoryRepository extends BaseRepository implements HospitalityCategoryRepositoryInterface
{
    public function __construct(HospitalityCategory $model)
    {
        parent::__construct($model);
    }
}
