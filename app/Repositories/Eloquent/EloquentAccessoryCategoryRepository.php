<?php

namespace App\Repositories\Eloquent;

use App\Models\AccessoryCategory;
use App\Repositories\Contracts\AccessoryCategoryRepositoryInterface;

class EloquentAccessoryCategoryRepository extends BaseRepository implements AccessoryCategoryRepositoryInterface
{
    public function __construct(AccessoryCategory $model)
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
