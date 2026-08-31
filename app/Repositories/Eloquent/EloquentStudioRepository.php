<?php

namespace App\Repositories\Eloquent;

use App\Models\Studio;
use App\Repositories\Contracts\StudioRepositoryInterface;

class EloquentStudioRepository extends BaseRepository implements StudioRepositoryInterface
{
    public function __construct(Studio $model)
    {
        parent::__construct($model);
    }

    public function listForCustomer(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->with(['category', 'images'])
            ->where('is_active', true);

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['min_capacity'])) {
            $query->where('capacity', '>=', (int) $filters['min_capacity']);
        }

        if (! empty($filters['guest_count'])) {
            $query->where('capacity', '>=', (int) $filters['guest_count']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }
}
