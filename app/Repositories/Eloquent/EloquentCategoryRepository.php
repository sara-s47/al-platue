<?php

namespace App\Repositories\Eloquent;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;

class EloquentCategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function allOrdered(): \Illuminate\Support\Collection
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
