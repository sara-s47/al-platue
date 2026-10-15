<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface StudioRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function orderedForCustomer(array $filters = []): Collection;

    public function nextSortOrder(): int;
}
