<?php

namespace App\Services\Equipment;

use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessException;
use App\Models\Equipment;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EquipmentService
{
    public function __construct(
        protected EquipmentRepositoryInterface $equipmentRepository,
        protected StudioRepositoryInterface $studioRepository,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->equipmentRepository->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->equipmentRepository->findOrFail($id);
    }

    public function create(array $data, array $imagePaths = []): Model
    {
        return DB::transaction(function () use ($data, $imagePaths) {
            $equipment = $this->equipmentRepository->create($this->normalizePayload($data));
            $this->syncImages($equipment->id, $imagePaths);

            return $equipment->fresh(['images']);
        });
    }

    public function update(int $id, array $data, ?array $imagePaths = null): Model
    {
        return DB::transaction(function () use ($id, $data, $imagePaths) {
            $equipment = $this->equipmentRepository->update($id, $this->normalizePayload($data));

            if ($imagePaths !== null) {
                $this->syncImages($equipment->id, $imagePaths);
            }

            return $equipment->fresh(['images']);
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $assigned = DB::table('studio_equipment')->where('equipment_id', $id)->exists();

            if ($assigned) {
                throw new BusinessException('Cannot delete equipment assigned to studios.', 'equipment_in_use');
            }

            $this->syncImages($id, []);

            return $this->equipmentRepository->delete($id);
        });
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
     * @return list<Equipment>
     */
    public function getForStudio(int $studioId): array
    {
        return Equipment::query()
            ->with('images')
            ->where('status', EquipmentStatus::Active)
            ->whereHas('studios', fn ($q) => $q->where('studios.id', $studioId))
            ->with(['studios' => fn ($q) => $q->where('studios.id', $studioId)])
            ->get()
            ->each(function (Equipment $equipment) {
                $equipment->setAttribute(
                    'studio_quantity',
                    $equipment->studios->first()?->pivot?->quantity,
                );
            })
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

        if (array_key_exists('status', $data)) {
            $status = strtolower(trim((string) ($data['status'] ?? '')));

            // Frontend alias: available → active
            if ($status === 'available' || $status === '') {
                $data['status'] = EquipmentStatus::Active->value;
            }
        }

        unset($data['image'], $data['images'], $data['clear_images']);

        return $data;
    }
}
