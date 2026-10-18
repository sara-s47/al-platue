<?php

namespace App\Repositories\Eloquent;

use App\Models\EquipmentRental;
use App\Repositories\Contracts\EquipmentRentalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentEquipmentRentalRepository extends BaseRepository implements EquipmentRentalRepositoryInterface
{
    public function __construct(EquipmentRental $model)
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
            ->with('items.equipment.images')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateFiltered(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with(['user', 'items.equipment'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($q, $method) => $q->where('payment_method', $method))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('end_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('start_date', '<=', $to))
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
