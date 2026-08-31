<?php

namespace App\Repositories\Eloquent;

use App\Models\Equipment;
use App\Repositories\Contracts\EquipmentRepositoryInterface;

class EloquentEquipmentRepository extends BaseRepository implements EquipmentRepositoryInterface
{
    public function __construct(Equipment $model)
    {
        parent::__construct($model);
    }
}
