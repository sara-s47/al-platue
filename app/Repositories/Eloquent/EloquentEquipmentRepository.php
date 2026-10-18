<?php

namespace App\Repositories\Eloquent;

use App\Enums\EquipmentStatus;
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
            ->with(['images', 'studios'])
            ->orderByDesc('id')
            ->paginate($perPage, $columns);
    }

    public function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with('images')
            ->where('status', EquipmentStatus::Active);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }
}
