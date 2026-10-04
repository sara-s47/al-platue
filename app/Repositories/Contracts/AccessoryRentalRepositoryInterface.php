<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AccessoryRentalRepositoryInterface extends BaseRepositoryInterface
{
    public function existsByRentalNumber(string $rentalNumber): bool;

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function paginateFiltered(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
