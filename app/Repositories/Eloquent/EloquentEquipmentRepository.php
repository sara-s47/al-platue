<?php

namespace App\Repositories\Eloquent;

use App\Models\Equipment;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class EloquentEquipmentRepository extends BaseRepository implements EquipmentRepositoryInterface
{
    public function __construct(Equipment $model)
    {
        parent::__construct($model);
    }

    public function find(int $id): ?Model
    {
        return $this->model->newQuery()->with('images')->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->model->newQuery()->with('images')->findOrFail($id);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with('images')
            ->orderByDesc('id')
            ->paginate($perPage, $columns);
    }
}
