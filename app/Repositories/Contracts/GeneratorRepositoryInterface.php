<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GeneratorRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
