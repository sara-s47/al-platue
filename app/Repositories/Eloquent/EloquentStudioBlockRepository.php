<?php

namespace App\Repositories\Eloquent;

use App\Models\StudioBlock;
use App\Repositories\Contracts\StudioBlockRepositoryInterface;

class EloquentStudioBlockRepository extends BaseRepository implements StudioBlockRepositoryInterface
{
    public function __construct(StudioBlock $model)
    {
        parent::__construct($model);
    }

    public function getOverlappingForStudio(int $studioId, string $startAt, string $endAt): \Illuminate\Support\Collection
    {
        return $this->model->newQuery()
            ->where('studio_id', $studioId)
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->get();
    }
}
