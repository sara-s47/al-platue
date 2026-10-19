<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RentalOrderRepositoryInterface extends BaseRepositoryInterface
{
    public function existsByOrderNumber(string $orderNumber): bool;

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function paginateFiltered(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
