<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface StudioScheduleOverrideRepositoryInterface extends BaseRepositoryInterface
{
    public function findForStudioAndDate(int $studioId, string $date): ?Model;
}
