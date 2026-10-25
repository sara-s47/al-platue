<?php

namespace App\Repositories\Eloquent;

use App\Models\Generator;
use App\Repositories\Contracts\GeneratorRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class EloquentGeneratorRepository extends BaseRepository implements GeneratorRepositoryInterface
{
    public function __construct(Generator $model)
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

    public function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with('images')
            ->where('is_active', true);

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
