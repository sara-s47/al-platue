<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AccessoryRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function paginateAdmin(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
