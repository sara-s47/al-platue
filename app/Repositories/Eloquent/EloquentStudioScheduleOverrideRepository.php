<?php

namespace App\Repositories\Eloquent;

use App\Models\StudioScheduleOverride;
use App\Repositories\Contracts\StudioScheduleOverrideRepositoryInterface;

class EloquentStudioScheduleOverrideRepository extends BaseRepository implements StudioScheduleOverrideRepositoryInterface
{
    public function __construct(StudioScheduleOverride $model)
    {
        parent::__construct($model);
    }

    public function findForStudioAndDate(int $studioId, string $date): ?StudioScheduleOverride
    {
        return $this->model->newQuery()
            ->where('studio_id', $studioId)
            ->whereDate('date', $date)
            ->first();
    }
}
