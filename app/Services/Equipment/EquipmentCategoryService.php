<?php

namespace App\Services\Equipment;

use App\Exceptions\BusinessException;
use App\Models\EquipmentCategory;
use App\Repositories\Contracts\EquipmentCategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EquipmentCategoryService
{
    public function __construct(
        protected EquipmentCategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->categoryRepository->paginate($perPage);
    }

    public function all(): Collection
    {
        return $this->categoryRepository->allOrdered();
    }

    public function find(int $id): EquipmentCategory
    {
        return $this->categoryRepository->findOrFail($id);
    }

    public function create(array $data): EquipmentCategory
    {
        return $this->categoryRepository->create($data);
    }

    public function update(int $id, array $data): EquipmentCategory
    {
        return $this->categoryRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        $hasItems = DB::table('equipment')->where('category_id', $id)->exists();

        if ($hasItems) {
            throw new BusinessException('Cannot delete category with existing equipment.', 'category_has_items');
        }

        return $this->categoryRepository->delete($id);
    }

    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                $this->categoryRepository->update((int) $id, [
                    'sort_order' => $index + 1,
                ]);
            }
        });
    }

    public function toggleActive(int $id): EquipmentCategory
    {
        $category = $this->categoryRepository->findOrFail($id);

        return $this->categoryRepository->update($id, [
            'is_active' => ! $category->is_active,
        ]);
    }
}
