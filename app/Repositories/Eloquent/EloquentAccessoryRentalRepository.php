<?php

namespace App\Repositories\Eloquent;

use App\Models\AccessoryRental;
use App\Repositories\Contracts\AccessoryRentalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentAccessoryRentalRepository extends BaseRepository implements AccessoryRentalRepositoryInterface
{
    public function __construct(AccessoryRental $model)
    {
        parent::__construct($model);
    }

    public function existsByRentalNumber(string $rentalNumber): bool
    {
        return $this->model->newQuery()->where('rental_number', $rentalNumber)->exists();
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->with('items.accessory.images')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateFiltered(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with(['user', 'items.accessory'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($q, $method) => $q->where('payment_method', $method))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('end_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('start_date', '<=', $to))
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
