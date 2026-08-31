<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface StudioBlockRepositoryInterface extends BaseRepositoryInterface
{
    public function getOverlappingForStudio(int $studioId, string $startAt, string $endAt): Collection;
}
