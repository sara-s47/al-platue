<?php

namespace App\Services\Hospitality;

use App\Exceptions\BusinessException;
use App\Repositories\Contracts\HospitalityCategoryRepositoryInterface;
use App\Repositories\Contracts\HospitalityItemRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class HospitalityService
{
    public function __construct(
        protected HospitalityCategoryRepositoryInterface $categoryRepository,
        protected HospitalityItemRepositoryInterface $itemRepository,
        protected StudioRepositoryInterface $studioRepository,
        protected InventoryService $inventoryService,
    ) {
    }

    public function paginateCategories(int $perPage = 15): LengthAwarePaginator
    {
        return $this->categoryRepository->paginate($perPage);
    }

    public function paginateItems(int $perPage = 15): LengthAwarePaginator
    {
        $paginator = $this->itemRepository->paginate($perPage);
        $paginator->getCollection()->load('studios:id,name');

        return $paginator;
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

    /**
     * @param  list<int>|null  $studioIds  null keeps links untouched; [] removes all links (offered in every studio)
     */
    public function createItem(array $data, ?array $studioIds = null): Model
    {
        $this->categoryRepository->findOrFail((int) $data['category_id']);

        return DB::transaction(function () use ($data, $studioIds) {
            $item = $this->itemRepository->create($data);

            if ($studioIds !== null) {
                $this->syncStudios($item->id, $studioIds);
            }

            return $item->fresh()->load('studios:id,name');
        });
    }

    /**
     * @param  list<int>|null  $studioIds  null keeps links untouched; [] removes all links (offered in every studio)
     */
    public function updateItem(int $id, array $data, ?array $studioIds = null): Model
    {
        if (isset($data['category_id'])) {
            $this->categoryRepository->findOrFail((int) $data['category_id']);
        }

        return DB::transaction(function () use ($id, $data, $studioIds) {
            $item = $this->itemRepository->update($id, $data);

            if ($studioIds !== null) {
                $this->syncStudios($item->id, $studioIds);
            }

            return $item->fresh()->load('studios:id,name');
        });
    }

    public function deleteItem(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->itemRepository->findOrFail($id);

            if (DB::table('booking_hospitality')->where('hospitality_item_id', $id)->exists()) {
                throw new BusinessException('Cannot delete a hospitality item used in bookings.', 'hospitality_in_use');
            }

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

    /**
     * Items linked to the studio plus items with no studio links (offered in every studio).
     * With a time window, items fully booked in this studio during that window are left out.
     */
    public function getForStudio(int $studioId, ?Carbon $startAt = null, ?Carbon $endAt = null): array
    {
        $this->studioRepository->findOrFail($studioId);

        $items = DB::table('hospitality_items')
            ->join('hospitality_categories', 'hospitality_categories.id', '=', 'hospitality_items.category_id')
            ->where('hospitality_items.is_active', true)
            ->where('hospitality_categories.is_active', true)
            ->where(function ($q) use ($studioId) {
                $q->whereExists(fn ($s) => $s->from('studio_hospitality')
                    ->whereColumn('studio_hospitality.hospitality_item_id', 'hospitality_items.id')
                    ->where('studio_hospitality.studio_id', $studioId))
                    ->orWhereNotExists(fn ($s) => $s->from('studio_hospitality')
                        ->whereColumn('studio_hospitality.hospitality_item_id', 'hospitality_items.id'));
            })
            ->select('hospitality_items.*', 'hospitality_categories.name as category_name')
            ->orderBy('hospitality_categories.name')
            ->orderBy('hospitality_items.name')
            ->get();

        if ($startAt === null || $endAt === null) {
            return $items->all();
        }

        return $items
            ->each(function ($item) use ($studioId, $startAt, $endAt) {
                $item->available_quantity = $this->inventoryService->getHospitalityStock(
                    $studioId,
                    (int) $item->id,
                    (int) $item->quantity,
                    $startAt,
                    $endAt,
                )['available'];
            })
            ->filter(fn ($item) => $item->available_quantity > 0)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $studioIds
     */
    protected function syncStudios(int $itemId, array $studioIds): void
    {
        $studioIds = array_values(array_unique(array_map('intval', $studioIds)));

        DB::table('studio_hospitality')
            ->where('hospitality_item_id', $itemId)
            ->whereNotIn('studio_id', $studioIds)
            ->delete();

        foreach ($studioIds as $studioId) {
            DB::table('studio_hospitality')->insertOrIgnore([
                'studio_id' => $studioId,
                'hospitality_item_id' => $itemId,
            ]);
        }
    }
}
