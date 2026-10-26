<?php

namespace App\Repositories\Eloquent;

use App\Models\EquipmentCategory;
use App\Repositories\Contracts\EquipmentCategoryRepositoryInterface;

class EloquentEquipmentCategoryRepository extends BaseRepository implements EquipmentCategoryRepositoryInterface
{
    public function __construct(EquipmentCategory $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage, $columns);
    }

    public function allOrdered(): \Illuminate\Support\Collection
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
