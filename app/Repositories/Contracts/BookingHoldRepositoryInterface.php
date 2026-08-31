<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface BookingHoldRepositoryInterface extends BaseRepositoryInterface
{
    public function getActiveOverlappingForStudio(int $studioId, string $startAt, string $endAt): Collection;

    public function expirePast(): int;
}
