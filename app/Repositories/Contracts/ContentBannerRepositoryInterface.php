<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContentBannerRepositoryInterface extends BaseRepositoryInterface
{
    public function getActiveOrdered(): Collection;

    public function paginateAdmin(int $perPage = 15): LengthAwarePaginator;

    public function paginateActive(int $perPage = 15): LengthAwarePaginator;
}
