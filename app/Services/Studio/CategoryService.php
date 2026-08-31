<?php

namespace App\Services\Studio;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function __construct(
        protected CategoryRepositoryInterface $categoryRepository,
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

    public function find(int $id): Category
    {
        return $this->categoryRepository->findOrFail($id);
    }

    public function create(array $data): Category
    {
        return $this->categoryRepository->create($data);
    }

    public function update(int $id, array $data): Category
    {
        return $this->categoryRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
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

    public function toggleActive(int $id): Category
    {
        $category = $this->categoryRepository->findOrFail($id);

        return $this->categoryRepository->update($id, [
            'is_active' => ! $category->is_active,
        ]);
    }
}
