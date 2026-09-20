<?php

namespace App\Repositories\Eloquent;

use App\Models\LoyaltyCampaign;
use App\Repositories\Contracts\LoyaltyCampaignRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentLoyaltyCampaignRepository extends BaseRepository implements LoyaltyCampaignRepositoryInterface
{
    public function __construct(LoyaltyCampaign $model)
    {
        parent::__construct($model);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with('creator')
            ->orderByDesc('id')
            ->paginate($perPage, $columns);
    }
}
