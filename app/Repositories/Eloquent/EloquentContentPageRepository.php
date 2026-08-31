<?php

namespace App\Repositories\Eloquent;

use App\Models\ContentPage;
use App\Repositories\Contracts\ContentPageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EloquentContentPageRepository extends BaseRepository implements ContentPageRepositoryInterface
{
    public function __construct(ContentPage $model)
    {
        parent::__construct($model);
    }

    public function findByKey(string $key): ?Model
    {
        return $this->model->newQuery()->where('key', $key)->first();
    }

    public function getActive(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('key')
            ->get();
    }

    public function paginateAdmin(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->orderBy('key')
            ->paginate($perPage);
    }
}
