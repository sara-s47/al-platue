<?php

namespace App\Repositories\Eloquent;

use App\Models\ContentBanner;
use App\Repositories\Contracts\ContentBannerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentContentBannerRepository extends BaseRepository implements ContentBannerRepositoryInterface
{
    public function __construct(ContentBanner $model)
    {
        parent::__construct($model);
    }

    public function getActiveOrdered(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function paginateAdmin(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateActive(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);
    }
}
