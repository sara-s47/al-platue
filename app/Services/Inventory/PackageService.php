<?php

namespace App\Services\Inventory;

use App\Exceptions\BusinessException;
use App\Repositories\Contracts\PackageRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PackageService
{
    public function __construct(
        protected PackageRepositoryInterface $packageRepository,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->packageRepository->paginate($perPage);
    }

    public function find(int $id): ?Model
    {
        return $this->packageRepository->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->packageRepository->findOrFail($id);
    }

    public function findWithDetails(int $id): Model
    {
        $package = $this->packageRepository->findOrFail($id);

        return $package->load(['equipment', 'hospitalityItems']);
    }

    /**
     * @return array<int, array{id: int, quantity: int}>
     */
    public function getIncludedEquipment(int $packageId): array
    {
        return collect($this->getPackageEquipment($packageId))
            ->map(fn (array $row) => ['id' => (int) $row['equipment_id'], 'quantity' => (int) $row['quantity']])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, quantity: int}>
     */
    public function getIncludedHospitality(int $packageId): array
    {
        return collect($this->getPackageHospitality($packageId))
            ->map(fn (array $row) => ['id' => (int) $row['hospitality_item_id'], 'quantity' => (int) $row['quantity']])
            ->values()
            ->all();
    }

    /**
     * Merge package includes with optional extras. Client quantities are treated as total desired.
     *
     * @param  array<int, array{id: int, quantity: int}>  $requested
     * @param  array<int, array{id: int, quantity: int}>  $included
     * @return array{merged: array<int, array{id: int, quantity: int}>, chargeable: array<int, array{id: int, quantity: int}>, included: array<int, array{id: int, quantity: int}>}
     */
    public function mergeInventoryItems(array $requested, array $included): array
    {
        $includedById = [];
        foreach ($included as $item) {
            $includedById[(int) $item['id']] = (int) $item['quantity'];
        }

        $requestedById = [];
        foreach ($requested as $item) {
            $requestedById[(int) $item['id']] = (int) $item['quantity'];
        }

        $allIds = array_unique(array_merge(array_keys($includedById), array_keys($requestedById)));
        $merged = [];
        $chargeable = [];

        foreach ($allIds as $id) {
            $includeQty = $includedById[$id] ?? 0;
            $requestQty = $requestedById[$id] ?? 0;
            $totalQty = max($includeQty, $requestQty);

            if ($totalQty > 0) {
                $merged[] = ['id' => $id, 'quantity' => $totalQty];
            }

            $extra = max(0, $totalQty - $includeQty);
            if ($extra > 0) {
                $chargeable[] = ['id' => $id, 'quantity' => $extra];
            }
        }

        return [
            'merged' => $merged,
            'chargeable' => $chargeable,
            'included' => collect($includedById)
                ->map(fn (int $qty, int $id) => ['id' => $id, 'quantity' => $qty])
                ->values()
                ->all(),
        ];
    }

    public function create(array $data, array $equipment = [], array $hospitality = []): Model
    {
        return DB::transaction(function () use ($data, $equipment, $hospitality) {
            $package = $this->packageRepository->create($data);
            $this->syncPackageItems($package->id, $equipment, $hospitality);

            return $package->fresh();
        });
    }

    public function update(int $id, array $data, ?array $equipment = null, ?array $hospitality = null): Model
    {
        return DB::transaction(function () use ($id, $data, $equipment, $hospitality) {
            $package = $this->packageRepository->update($id, $data);

            if ($equipment !== null || $hospitality !== null) {
                $this->syncPackageItems(
                    $package->id,
                    $equipment ?? $this->getPackageEquipment($package->id),
                    $hospitality ?? $this->getPackageHospitality($package->id),
                );
            }

            return $package->fresh();
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            DB::table('package_equipment')->where('package_id', $id)->delete();
            DB::table('package_hospitality')->where('package_id', $id)->delete();

            return $this->packageRepository->delete($id);
        });
    }

    public function getForStudio(int $studioId): Collection
    {
        return $this->packageRepository->listForStudio($studioId);
    }

    public function validatePackageItems(int $packageId, int $studioId): void
    {
        $package = $this->packageRepository->findOrFail($packageId);

        if ((int) $package->studio_id !== $studioId) {
            throw new BusinessException('Package does not belong to this studio.', 'package_studio_mismatch');
        }

        if (! $package->is_active) {
            throw new BusinessException('Package is not active.', 'package_inactive');
        }

        $today = Carbon::today();

        if ($package->valid_from && Carbon::parse($package->valid_from)->gt($today)) {
            throw new BusinessException('Package is not yet valid.', 'package_not_valid');
        }

        if ($package->valid_to && Carbon::parse($package->valid_to)->lt($today)) {
            throw new BusinessException('Package has expired.', 'package_expired');
        }

        $equipment = DB::table('package_equipment')
            ->join('studio_equipment', function ($join) use ($studioId) {
                $join->on('studio_equipment.equipment_id', '=', 'package_equipment.equipment_id')
                    ->where('studio_equipment.studio_id', '=', $studioId);
            })
            ->where('package_equipment.package_id', $packageId)
            ->count();

        $expectedEquipment = DB::table('package_equipment')->where('package_id', $packageId)->count();

        if ($equipment !== $expectedEquipment) {
            throw new BusinessException('Package includes equipment not available at this studio.', 'package_equipment_invalid');
        }

        $hospitality = DB::table('package_hospitality')
            ->join('studio_hospitality', function ($join) use ($studioId) {
                $join->on('studio_hospitality.hospitality_item_id', '=', 'package_hospitality.hospitality_item_id')
                    ->where('studio_hospitality.studio_id', '=', $studioId);
            })
            ->where('package_hospitality.package_id', $packageId)
            ->count();

        $expectedHospitality = DB::table('package_hospitality')->where('package_id', $packageId)->count();

        if ($hospitality !== $expectedHospitality) {
            throw new BusinessException('Package includes hospitality not available at this studio.', 'package_hospitality_invalid');
        }
    }

    protected function syncPackageItems(int $packageId, array $equipment, array $hospitality): void
    {
        DB::table('package_equipment')->where('package_id', $packageId)->delete();
        DB::table('package_hospitality')->where('package_id', $packageId)->delete();

        foreach ($equipment as $item) {
            DB::table('package_equipment')->insert([
                'package_id' => $packageId,
                'equipment_id' => $item['equipment_id'] ?? $item['id'],
                'quantity' => $item['quantity'],
            ]);
        }

        foreach ($hospitality as $item) {
            DB::table('package_hospitality')->insert([
                'package_id' => $packageId,
                'hospitality_item_id' => $item['hospitality_item_id'] ?? $item['id'],
                'quantity' => $item['quantity'],
            ]);
        }
    }

    protected function getPackageEquipment(int $packageId): array
    {
        return DB::table('package_equipment')
            ->where('package_id', $packageId)
            ->get()
            ->map(fn ($row) => ['equipment_id' => $row->equipment_id, 'quantity' => $row->quantity])
            ->all();
    }

    protected function getPackageHospitality(int $packageId): array
    {
        return DB::table('package_hospitality')
            ->where('package_id', $packageId)
            ->get()
            ->map(fn ($row) => ['hospitality_item_id' => $row->hospitality_item_id, 'quantity' => $row->quantity])
            ->all();
    }
}
