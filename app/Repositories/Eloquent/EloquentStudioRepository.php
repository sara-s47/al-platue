<?php

namespace App\Repositories\Eloquent;

use App\Models\Studio;
use App\Repositories\Contracts\StudioRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentStudioRepository extends BaseRepository implements StudioRepositoryInterface
{
    public function __construct(Studio $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with(['images', 'pricingRules' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage, $columns);
    }

    public function listForCustomer(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $this->customerQuery($filters)->orderBy('name')->paginate($perPage);
    }

    public function orderedForCustomer(array $filters = []): Collection
    {
        return $this->customerQuery($filters)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function nextSortOrder(): int
    {
        return ((int) $this->model->newQuery()->max('sort_order')) + 1;
    }

    protected function customerQuery(array $filters): Builder
    {
        $query = $this->model->newQuery()
            ->with([
                'category',
                'images',
                'pricingRules' => fn ($q) => $q->where('is_active', true),
            ])
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

        return $query;
    }
}
