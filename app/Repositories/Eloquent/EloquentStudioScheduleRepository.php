<?php

namespace App\Repositories\Eloquent;

use App\Models\StudioSchedule;
use App\Repositories\Contracts\StudioScheduleRepositoryInterface;

class EloquentStudioScheduleRepository extends BaseRepository implements StudioScheduleRepositoryInterface
{
    public function __construct(StudioSchedule $model)
    {
        parent::__construct($model);
    }

    public function getForStudio(int $studioId): \Illuminate\Support\Collection
    {
        return $this->model->newQuery()
            ->where('studio_id', $studioId)
            ->orderBy('day_of_week')
            ->get();
    }

    public function findForStudioAndDay(int $studioId, int $dayOfWeek): ?StudioSchedule
    {
        return $this->model->newQuery()
            ->where('studio_id', $studioId)
            ->where('day_of_week', $dayOfWeek)
            ->first();
    }
}
