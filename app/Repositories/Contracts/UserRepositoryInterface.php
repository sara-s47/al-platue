<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByPhone(string $phone): ?Model;

    public function findByEmail(string $email): ?Model;

    /**
     * @param  array{status?: string, search?: string}  $filters
     */
    public function paginateAdmin(int $perPage = 15, array $filters = []): LengthAwarePaginator;
}
