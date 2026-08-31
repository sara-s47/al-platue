<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface StudioScheduleRepositoryInterface extends BaseRepositoryInterface
{
    public function getForStudio(int $studioId): Collection;

    public function findForStudioAndDay(int $studioId, int $dayOfWeek): ?Model;
}
