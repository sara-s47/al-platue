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
            ->with(['user', 'items.equipment.images'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($q, $method) => $q->where('payment_method', $method))
            // from/to = order created_at (dashboard date range), not rental period
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('rental_number', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
