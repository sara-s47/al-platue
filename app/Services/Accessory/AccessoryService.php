<?php

namespace App\Services\Accessory;

use App\Enums\BookingStatus;
use App\Enums\RentalOrderStatus;
use App\Exceptions\BusinessException;
use App\Models\Accessory;
use App\Models\AccessoryImage;
use App\Models\AccessoryRentalItem;
use App\Repositories\Contracts\AccessoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AccessoryService
{
    public function __construct(
        protected AccessoryRepositoryInterface $accessoryRepository,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $paginator = $this->accessoryRepository->paginate($perPage);
        $this->attachStats($paginator->getCollection()->all());

        return $paginator;
    }

    public function find(int $id): Accessory
    {
        /** @var Accessory $accessory */
        $accessory = $this->accessoryRepository->findOrFail($id);
        $this->attachStats([$accessory]);

        return $accessory;
    }

    /**
     * @param  list<string>  $imagePaths
     */
    public function create(array $data, array $imagePaths = []): Accessory
    {
        return DB::transaction(function () use ($data, $imagePaths) {
            $accessory = $this->accessoryRepository->create($data);
            $this->syncImages($accessory->id, $imagePaths);

            return $this->find($accessory->id);
        });
    }

    /**
     * @param  list<string>|null  $imagePaths  null keeps current images; an array replaces them.
     */
    public function update(int $id, array $data, ?array $imagePaths = null): Accessory
    {
        return DB::transaction(function () use ($id, $data, $imagePaths) {
            $this->accessoryRepository->update($id, $data);

            if ($imagePaths !== null) {
                $this->syncImages($id, $imagePaths);
            }

            return $this->find($id);
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->accessoryRepository->findOrFail($id);

            if (AccessoryRentalItem::query()->where('accessory_id', $id)->exists()) {
                throw new BusinessException(
                    'Accessory has rental history and cannot be deleted. Deactivate it instead.',
                    'accessory_has_rentals',
                );
            }

            $this->syncImages($id, []);

            return $this->accessoryRepository->delete($id);
        });
    }

    /**
     * Rental history rows (standalone and with studio), newest first.
     */
    public function rentalHistory(int $accessoryId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $this->accessoryRepository->findOrFail($accessoryId);

        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        return AccessoryRentalItem::query()
            ->where('accessory_id', $accessoryId)
            ->with(['booking.user', 'rentalOrder.user', 'studio'])
            ->when($type === 'with_studio', fn ($q) => $q->whereNotNull('booking_id'))
            ->when($type === 'standalone', fn ($q) => $q->whereNotNull('rental_order_id'))
            ->when($status, fn ($q) => $q->where(function ($q) use ($status) {
                $q->whereHas('booking', fn ($b) => $b->where('status', $status))
                    ->orWhereHas('rentalOrder', fn ($r) => $r->where('status', $status));
            }))
            ->when($from, fn ($q) => $q->where(function ($q) use ($from) {
                $q->whereHas('booking', fn ($b) => $b->whereDate('end_at', '>=', $from))
                    ->orWhereHas('rentalOrder', fn ($r) => $r->where('end_date', '>=', $from));
            }))
            ->when($to, fn ($q) => $q->where(function ($q) use ($to) {
                $q->whereHas('booking', fn ($b) => $b->whereDate('start_at', '<=', $to))
                    ->orWhereHas('rentalOrder', fn ($r) => $r->where('start_date', '<=', $to));
            }))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Sets a `stats` attribute on each accessory. Cancelled bookings and rentals are excluded.
     *
     * @param  list<Accessory>  $accessories
     */
    public function attachStats(array $accessories): void
    {
        $ids = array_map(fn (Accessory $a) => $a->id, $accessories);

        if ($ids === []) {
            return;
        }

        $rows = DB::table('accessory_rental_items')
            ->leftJoin('bookings', 'bookings.id', '=', 'accessory_rental_items.booking_id')
            ->leftJoin('rental_orders', 'rental_orders.id', '=', 'accessory_rental_items.rental_order_id')
            ->whereIn('accessory_rental_items.accessory_id', $ids)
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereNotNull('accessory_rental_items.booking_id')
                        ->where('bookings.status', '!=', BookingStatus::Cancelled->value);
                })->orWhere(function ($q) {
                    $q->whereNotNull('accessory_rental_items.rental_order_id')
                        ->where('rental_orders.status', '!=', RentalOrderStatus::Cancelled->value);
                });
            })
            ->groupBy('accessory_rental_items.accessory_id')
            ->select([
                'accessory_rental_items.accessory_id',
                DB::raw('COUNT(*) as times_rented'),
                DB::raw('SUM(CASE WHEN accessory_rental_items.booking_id IS NULL THEN 1 ELSE 0 END) as times_rented_standalone'),
                DB::raw('SUM(CASE WHEN accessory_rental_items.booking_id IS NOT NULL THEN 1 ELSE 0 END) as times_rented_with_studio'),
                DB::raw('SUM(accessory_rental_items.quantity) as total_quantity_rented'),
                DB::raw('SUM(accessory_rental_items.total_price) as total_revenue'),
            ])
            ->get()
            ->keyBy('accessory_id');

        foreach ($accessories as $accessory) {
            $row = $rows->get($accessory->id);

            $accessory->setAttribute('stats', [
                'times_rented' => (int) ($row->times_rented ?? 0),
                'times_rented_standalone' => (int) ($row->times_rented_standalone ?? 0),
                'times_rented_with_studio' => (int) ($row->times_rented_with_studio ?? 0),
                'total_quantity_rented' => (int) ($row->total_quantity_rented ?? 0),
                'total_revenue' => round((float) ($row->total_revenue ?? 0), 2),
            ]);
        }
    }

    /**
     * @param  list<string>  $imagePaths
     */
    protected function syncImages(int $accessoryId, array $imagePaths): void
    {
        $existing = AccessoryImage::query()->where('accessory_id', $accessoryId)->get();

        foreach ($existing as $image) {
            Storage::disk('public')->delete($image->path);
        }

        AccessoryImage::query()->where('accessory_id', $accessoryId)->delete();

        foreach (array_values($imagePaths) as $index => $path) {
            AccessoryImage::query()->create([
                'accessory_id' => $accessoryId,
                'path' => $path,
                'sort_order' => $index,
            ]);
        }
    }
}
