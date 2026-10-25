<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Repositories\Contracts\RentalOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentRentalOrderRepository extends BaseRepository implements RentalOrderRepositoryInterface
{
    public function __construct(Order $model)
    {
        parent::__construct($model);
    }

    public function existsByOrderNumber(string $orderNumber): bool
    {
        return $this->model->newQuery()->where('order_number', $orderNumber)->exists();
    }

    public function paginateForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->with([
                'accessoryItems.accessory.images',
                'equipmentItems.equipment.images',
                'portableBathroomItems.portableBathroom.images',
                'caravanItems.caravan.images',
                'generatorItems.generator.images',
                'booking',
                'paintColor',
            ])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateFiltered(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with([
                'user',
                'accessoryItems.accessory.images',
                'equipmentItems.equipment.images',
                'portableBathroomItems.portableBathroom.images',
                'caravanItems.caravan.images',
                'generatorItems.generator.images',
                'booking',
                'paintColor',
            ])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($q, $method) => $q->where('payment_method', $method))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
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
