<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class EloquentUserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function find(int $id): ?Model
    {
        return $this->model->newQuery()->with('roles')->find($id);
    }

    // public function findOrFail(int $id): Model
    // {
    //     return $this->model->newQuery()->with('roles')->findOrFail($id);
    // }

    public function findByPhone(string $phone): ?User
    {
        return $this->model->newQuery()->with('roles')->where('phone', $phone)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->newQuery()->with('roles')->where('email', $email)->first();
    }

    public function paginateAdmin(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with('roles')
            ->role('customer')
            ->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($perPage < 1) {
            $perPage = max(1, (clone $query)->count());
        }

        return $query->paginate($perPage);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->role('customer')
            ->with('roles')
            ->orderByDesc('id')
            ->paginate($perPage, $columns);
    }

    public function findOrFail(int $id): Model
    {
        return $this->model->newQuery()->with('roles')->findOrFail($id);
    }
}
