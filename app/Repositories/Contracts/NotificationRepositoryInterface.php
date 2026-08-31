<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface extends BaseRepositoryInterface
{
    public function findDueScheduled(): Collection;

    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator;
}
