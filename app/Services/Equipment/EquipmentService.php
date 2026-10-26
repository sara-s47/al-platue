<?php

namespace App\Services\Equipment;

use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessException;
use App\Models\BookingEquipment;
use App\Models\Equipment;
use App\Models\EquipmentRentalItem;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EquipmentService
{
    public function __construct(
        protected EquipmentRepositoryInterface $equipmentRepository,
        protected StudioRepositoryInterface $studioRepository,
        protected InventoryService $inventoryService,
    ) {
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->equipmentRepository->paginateAdmin($filters, $perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->equipmentRepository->findOrFail($id)->load(['studios', 'category']);
    }

    /**
     * @param  list<array{id: int, quantity?: int|null}>|null  $studios  null keeps links untouched; [] removes all links (shared by every studio)
     */
    public function create(array $data, array $imagePaths = [], ?array $studios = null): Model
    {
        return DB::transaction(function () use ($data, $imagePaths, $studios) {
            $equipment = $this->equipmentRepository->create($this->normalizePayload(['description' => null, ...$data]));
            $this->syncImages($equipment->id, $imagePaths);

            if ($studios !== null) {
                $this->syncStudios($equipment->id, (int) $equipment->quantity, $studios);
            }

            return $equipment->fresh(['images', 'studios']);
        });
    }

    /**
     * @param  list<array{id: int, quantity?: int|null}>|null  $studios  null keeps links untouched; [] removes all links (shared by every studio)
     */
    public function update(int $id, array $data, ?array $imagePaths = null, ?array $studios = null): Model
    {
        return DB::transaction(function () use ($id, $data, $imagePaths, $studios) {
            /** @var Equipment $current */
            $current = $this->equipmentRepository->findOrFail($id);
            $payload = $this->normalizePayload($data);
            $this->replaceCover($current, $payload);
            $equipment = $this->equipmentRepository->update($id, $payload);

            if ($imagePaths !== null) {
                $this->syncImages($equipment->id, $imagePaths);
            }

            if ($studios !== null) {
                $this->syncStudios($equipment->id, (int) $equipment->quantity, $studios);
            } else {
                $this->ensureLinksWithinTotal($equipment->id, (int) $equipment->quantity);
            }

            return $equipment->fresh(['images', 'studios']);
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            /** @var Equipment $equipment */
            $equipment = $this->equipmentRepository->findOrFail($id);

            if (DB::table('booking_equipment')->where('equipment_id', $id)->exists()) {
                throw new BusinessException('Cannot delete equipment used in bookings.', 'equipment_in_use');
            }

            if (DB::table('equipment_rental_items')->where('equipment_id', $id)->exists()) {
                throw new BusinessException('Cannot delete equipment used in rentals.', 'equipment_in_use');
            }

            DB::table('studio_equipment')->where('equipment_id', $id)->delete();
            $this->syncImages($id, []);
            $this->deleteCoverFile($equipment->cover_image);

            return $this->equipmentRepository->delete($id);
        });
    }

    /**
     * Rental history: standalone equipment_rentals and/or studio booking allocations.
     */
    public function rentalHistory(int $equipmentId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $this->equipmentRepository->findOrFail($equipmentId);

        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if ($type === 'with_studio') {
            return BookingEquipment::query()
                ->where('equipment_id', $equipmentId)
                ->with(['booking.user', 'booking.studio'])
                ->when($status, fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('status', $status)))
                ->when($from, fn ($q) => $q->whereHas('booking', fn ($b) => $b->whereDate('end_at', '>=', $from)))
                ->when($to, fn ($q) => $q->whereHas('booking', fn ($b) => $b->whereDate('start_at', '<=', $to)))
                ->orderByDesc('id')
                ->paginate($perPage);
        }

        return EquipmentRentalItem::query()
            ->where('equipment_id', $equipmentId)
            ->with(['rentalOrder.user'])
            ->when($status, fn ($q) => $q->whereHas('rentalOrder', fn ($r) => $r->where('status', $status)))
            ->when($from, fn ($q) => $q->whereHas('rentalOrder', fn ($r) => $r->where('end_date', '>=', $from)))
            ->when($to, fn ($q) => $q->whereHas('rentalOrder', fn ($r) => $r->where('start_date', '<=', $to)))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  list<array{id: int, quantity?: int|null}>  $studios
     */
    protected function syncStudios(int $equipmentId, int $totalQuantity, array $studios): void
    {
        $rows = [];

        foreach ($studios as $studio) {
            $studioId = (int) $studio['id'];
            $quantity = isset($studio['quantity']) ? (int) $studio['quantity'] : $totalQuantity;

            if ($quantity < 1) {
                throw new BusinessException('Studio quantity must be at least 1.', 'invalid_quantity');
            }

            if ($quantity > $totalQuantity) {
                throw new BusinessException(
                    'Studio quantity cannot exceed total equipment quantity.',
                    'quantity_exceeds_total',
                    422,
                    null,
                    ['studio_id' => $studioId, 'quantity' => $quantity, 'total_quantity' => $totalQuantity],
                );
            }

            $rows[$studioId] = $quantity;
        }

        DB::table('studio_equipment')
            ->where('equipment_id', $equipmentId)
            ->whereNotIn('studio_id', array_keys($rows))
            ->delete();

        foreach ($rows as $studioId => $quantity) {
            DB::table('studio_equipment')->updateOrInsert(
                ['studio_id' => $studioId, 'equipment_id' => $equipmentId],
                ['quantity' => $quantity],
            );
        }
    }

    protected function ensureLinksWithinTotal(int $equipmentId, int $totalQuantity): void
    {
        $exceeding = DB::table('studio_equipment')
            ->where('equipment_id', $equipmentId)
            ->where('quantity', '>', $totalQuantity)
            ->pluck('studio_id')
            ->all();

        if ($exceeding !== []) {
            throw new BusinessException(
                'Studio quantity cannot exceed total equipment quantity. Update the studio quantities as well.',
                'quantity_exceeds_total',
                422,
                null,
                ['studio_ids' => $exceeding, 'total_quantity' => $totalQuantity],
            );
        }
    }

    public function assignToStudio(int $equipmentId, int $studioId, int $quantity): void
    {
        DB::transaction(function () use ($equipmentId, $studioId, $quantity) {
            $this->studioRepository->findOrFail($studioId);
            $equipment = $this->equipmentRepository->findOrFail($equipmentId);

            $status = $equipment->status instanceof EquipmentStatus
                ? $equipment->status
                : EquipmentStatus::tryFrom((string) $equipment->status);

            if ($status !== EquipmentStatus::Active) {
                throw new BusinessException('Only active equipment can be assigned to studios.', 'equipment_not_active');
            }

            if ($quantity < 1) {
                throw new BusinessException('Studio quantity must be at least 1.', 'invalid_quantity');
            }

            if ($quantity > (int) $equipment->quantity) {
                throw new BusinessException('Studio quantity cannot exceed total equipment quantity.', 'quantity_exceeds_total');
            }

            DB::table('studio_equipment')->updateOrInsert(
                ['studio_id' => $studioId, 'equipment_id' => $equipmentId],
                ['quantity' => $quantity],
            );
        });
    }

    public function unassignFromStudio(int $equipmentId, int $studioId): void
    {
        DB::table('studio_equipment')
            ->where('studio_id', $studioId)
            ->where('equipment_id', $equipmentId)
            ->delete();
    }

    /**
     * Equipment linked to the studio plus equipment with no studio links (shared by every studio).
     * With a time window, items fully booked in that window are left out.
     *
     * @return list<Equipment>
     */
    public function getForStudio(int $studioId, ?Carbon $startAt = null, ?Carbon $endAt = null): array
    {
        $this->studioRepository->findOrFail($studioId);

        $items = Equipment::query()
            ->with(['images', 'category'])
            ->where('status', EquipmentStatus::Active)
            ->where(function ($q) use ($studioId) {
                $q->whereHas('studios', fn ($s) => $s->where('studios.id', $studioId))
                    ->orWhereDoesntHave('studios');
            })
            ->orderBy('name')
            ->get();

        if ($startAt === null || $endAt === null) {
            return $items->all();
        }

        return $items
            ->each(function (Equipment $equipment) use ($studioId, $startAt, $endAt) {
                $stock = $this->inventoryService->getEquipmentStock(
                    $studioId,
                    (int) $equipment->id,
                    (int) $equipment->quantity,
                    $startAt,
                    $endAt,
                );

                $equipment->setAttribute('available_quantity', $stock['available']);
            })
            ->filter(fn (Equipment $equipment) => $equipment->available_quantity > 0)
            ->values()
            ->all();
    }

    protected function syncImages(int $equipmentId, array $imagePaths): void
    {
        $existing = DB::table('equipment_images')
            ->where('equipment_id', $equipmentId)
            ->pluck('path')
            ->all();

        foreach ($existing as $path) {
            Storage::disk('public')->delete($path);
        }

        DB::table('equipment_images')->where('equipment_id', $equipmentId)->delete();

        $now = now();

        foreach ($imagePaths as $path) {
            DB::table('equipment_images')->insert([
                'equipment_id' => $equipmentId,
                'path' => $path,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Map API fields to DB columns / enum values.
     */
    protected function normalizePayload(array $data): array
    {
        if (array_key_exists('price_per_hour', $data)) {
            $data['price'] = $data['price_per_hour'];
            unset($data['price_per_hour']);
        }

        if (array_key_exists('price', $data) && ! array_key_exists('price_per_day', $data)) {
            $data['price_per_day'] = $data['price'];
        }

        if (array_key_exists('status', $data)) {
            $status = strtolower(trim((string) ($data['status'] ?? '')));

            // Frontend alias: available → active
            if ($status === 'available' || $status === '') {
                $data['status'] = EquipmentStatus::Active->value;
            }
        }

        if (array_key_exists('description', $data) && $data['description'] === null) {
            $data['description'] = '';
        }

        unset($data['image'], $data['images'], $data['clear_images'], $data['studios'], $data['clear_studios'], $data['clear_cover']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function replaceCover(Equipment $current, array $data): void
    {
        if (! array_key_exists('cover_image', $data)) {
            return;
        }

        $newPath = $data['cover_image'];
        if ($current->cover_image && $current->cover_image !== $newPath) {
            $this->deleteCoverFile($current->cover_image);
        }
    }

    protected function deleteCoverFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
