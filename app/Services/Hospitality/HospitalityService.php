<?php

namespace App\Services\Hospitality;

use App\Exceptions\BusinessException;
use App\Repositories\Contracts\HospitalityCategoryRepositoryInterface;
use App\Repositories\Contracts\HospitalityItemRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class HospitalityService
{
    public function __construct(
        protected HospitalityCategoryRepositoryInterface $categoryRepository,
        protected HospitalityItemRepositoryInterface $itemRepository,
        protected StudioRepositoryInterface $studioRepository,
    ) {
    }

    public function paginateCategories(int $perPage = 15): LengthAwarePaginator
    {
        return $this->categoryRepository->paginate($perPage);
    }

    public function paginateItems(int $perPage = 15): LengthAwarePaginator
    {
        return $this->itemRepository->paginate($perPage);
    }

    public function createCategory(array $data): Model
    {
        return $this->categoryRepository->create($data);
    }

    public function updateCategory(int $id, array $data): Model
    {
        return $this->categoryRepository->update($id, $data);
    }

    public function deleteCategory(int $id): bool
    {
        $hasItems = DB::table('hospitality_items')->where('category_id', $id)->exists();

        if ($hasItems) {
            throw new BusinessException('Cannot delete category with existing items.', 'category_has_items');
        }

        return $this->categoryRepository->delete($id);
    }

    public function createItem(array $data): Model
    {
        $this->categoryRepository->findOrFail((int) $data['category_id']);

        return $this->itemRepository->create($data);
    }

    public function updateItem(int $id, array $data): Model
    {
        if (isset($data['category_id'])) {
            $this->categoryRepository->findOrFail((int) $data['category_id']);
        }

        return $this->itemRepository->update($id, $data);
    }

    public function deleteItem(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            DB::table('studio_hospitality')->where('hospitality_item_id', $id)->delete();

            return $this->itemRepository->delete($id);
        });
    }

    public function assignToStudio(int $itemId, int $studioId): void
    {
        DB::transaction(function () use ($itemId, $studioId) {
            $this->studioRepository->findOrFail($studioId);
            $item = $this->itemRepository->findOrFail($itemId);

            if (! $item->is_active) {
                throw new BusinessException('Only active hospitality items can be assigned.', 'hospitality_not_active');
            }

            DB::table('studio_hospitality')->updateOrInsert(
                ['studio_id' => $studioId, 'hospitality_item_id' => $itemId],
                [],
            );
        });
    }

    public function unassignFromStudio(int $itemId, int $studioId): void
    {
        DB::table('studio_hospitality')
            ->where('studio_id', $studioId)
            ->where('hospitality_item_id', $itemId)
            ->delete();
    }

    public function getForStudio(int $studioId): array
    {
        return DB::table('hospitality_items')
            ->join('hospitality_categories', 'hospitality_categories.id', '=', 'hospitality_items.category_id')
            ->join('studio_hospitality', 'studio_hospitality.hospitality_item_id', '=', 'hospitality_items.id')
            ->where('studio_hospitality.studio_id', $studioId)
            ->where('hospitality_items.is_active', true)
            ->where('hospitality_categories.is_active', true)
            ->select('hospitality_items.*', 'hospitality_categories.name as category_name')
            ->orderBy('hospitality_categories.name')
            ->orderBy('hospitality_items.name')
            ->get()
            ->all();
    }
}
