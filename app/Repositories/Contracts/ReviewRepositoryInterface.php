<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface ReviewRepositoryInterface extends BaseRepositoryInterface
{
    public function findByBookingId(int $bookingId): ?Model;

    public function listForStudio(int $studioId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function paginateAdmin(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator;
}
