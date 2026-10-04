<?php

namespace App\Repositories\Eloquent;

use App\Models\InteriorDesigner;
use App\Repositories\Contracts\InteriorDesignerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentInteriorDesignerRepository extends BaseRepository implements InteriorDesignerRepositoryInterface
{
    public function __construct(InteriorDesigner $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage, $columns);
    }

    public function listActive(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
