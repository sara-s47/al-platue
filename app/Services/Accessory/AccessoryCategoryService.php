<?php

namespace App\Services\Accessory;

use App\Exceptions\BusinessException;
use App\Models\AccessoryCategory;
use App\Repositories\Contracts\AccessoryCategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccessoryCategoryService
{
    public function __construct(
        protected AccessoryCategoryRepositoryInterface $categoryRepository,
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

    public function find(int $id): AccessoryCategory
    {
        return $this->categoryRepository->findOrFail($id);
    }

    public function create(array $data): AccessoryCategory
    {
        return $this->categoryRepository->create($data);
    }

    public function update(int $id, array $data): AccessoryCategory
    {
        return $this->categoryRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        $hasItems = DB::table('accessories')->where('category_id', $id)->exists();

        if ($hasItems) {
            throw new BusinessException('Cannot delete category with existing accessories.', 'category_has_items');
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

    public function toggleActive(int $id): AccessoryCategory
    {
        $category = $this->categoryRepository->findOrFail($id);

        return $this->categoryRepository->update($id, [
            'is_active' => ! $category->is_active,
        ]);
    }
}
