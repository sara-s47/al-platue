<?php

namespace App\Repositories\Eloquent;

use App\Models\Package;
use App\Repositories\Contracts\PackageRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EloquentPackageRepository extends BaseRepository implements PackageRepositoryInterface
{
    public function __construct(Package $model)
    {
        parent::__construct($model);
    }

    public function listForStudio(int $studioId): Collection
    {
        $today = Carbon::today();

        return $this->model->newQuery()
            ->with(['equipment', 'hospitalityItems'])
            ->where('studio_id', $studioId)
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            })
            ->orderBy('name')
            ->get();
    }
}
