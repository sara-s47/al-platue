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
        $today = Carbon::today();

        return DB::table('packages')
            ->where('studio_id', $studioId)
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            })
            ->orderBy('name')
            ->get();
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
