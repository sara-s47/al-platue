<?php

namespace App\Repositories\Eloquent;

use App\Models\PricingRule;
use App\Repositories\Contracts\PricingRuleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class EloquentPricingRuleRepository extends BaseRepository implements PricingRuleRepositoryInterface
{
    public function __construct(PricingRule $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with('studio:id,name')
            ->orderByDesc('id')
            ->paginate($perPage, $columns);
    }

    public function findOrFail(int $id): Model
    {
        return $this->model->newQuery()->with('studio:id,name')->findOrFail($id);
    }

    public function create(array $data): Model
    {
        $rule = parent::create($data);

        return $rule->load('studio:id,name');
    }

    public function update(int $id, array $data): Model
    {
        $rule = parent::update($id, $data);

        return $rule->load('studio:id,name');
    }
}
