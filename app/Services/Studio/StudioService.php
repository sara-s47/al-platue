<?php

namespace App\Services\Studio;

use App\Models\Studio;
use App\Models\StudioImage;
use App\Repositories\Contracts\StudioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudioService
{
    public function __construct(
        protected StudioRepositoryInterface $studioRepository,
        protected ScheduleService $scheduleService,
    ) {
    }

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->studioRepository->paginate($perPage);
    }

    public function find(int $id): Studio
    {
        return $this->studioRepository->findOrFail($id);
    }

    public function create(array $data, array $imagePaths = []): Studio
    {
        $weeklySchedule = $data['weekly_schedule'] ?? null;
        unset($data['weekly_schedule'], $data['image'], $data['images']);

        $data = $this->normalizeTextColumns(['description' => null, 'address' => null, 'rules' => null, ...$data]);
        $position = isset($data['sort_order']) ? (int) $data['sort_order'] : null;
        unset($data['sort_order']);

        return DB::transaction(function () use ($data, $weeklySchedule, $imagePaths, $position) {
            $studio = $this->studioRepository->create([
                ...$data,
                'sort_order' => $this->studioRepository->nextSortOrder(),
            ]);

            if ($position !== null) {
                $this->moveToPosition($studio->id, $position);
            }

            if (is_array($weeklySchedule) && $weeklySchedule !== []) {
                $this->scheduleService->setWeeklySchedule($studio->id, $weeklySchedule);
            }

            $this->syncImages($studio->id, $imagePaths);

            return $studio->fresh(['images']);
        });
    }

    /**
     * @param  list<string>|null  $imagePaths  null keeps current images; an array replaces them.
     */
    public function update(int $id, array $data, ?array $imagePaths = null): Studio
    {
        unset($data['image'], $data['images'], $data['clear_images']);
        $data = $this->normalizeTextColumns($data);
        $position = isset($data['sort_order']) ? (int) $data['sort_order'] : null;
        unset($data['sort_order']);

        return DB::transaction(function () use ($id, $data, $imagePaths, $position) {
            $studio = $this->studioRepository->update($id, $data);

            if ($position !== null) {
                $this->moveToPosition($studio->id, $position);
            }

            if ($imagePaths !== null) {
                $this->syncImages($studio->id, $imagePaths);
            }

            return $studio->fresh(['images']);
        });
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $deleted = $this->studioRepository->delete($id);
            $this->applyOrder($this->orderedIdsExcept([]));

            return $deleted;
        });
    }

    public function toggleActive(int $id): Studio
    {
        $studio = $this->studioRepository->findOrFail($id);

        return $this->studioRepository->update($id, [
            'is_active' => ! $studio->is_active,
        ]);
    }

    public function uploadImages(int $studioId, array $images): Studio
    {
        $studio = $this->studioRepository->findOrFail($studioId);

        DB::transaction(function () use ($studio, $images) {
            foreach ($images as $image) {
                StudioImage::query()->create([
                    'studio_id' => $studio->id,
                    'path' => $image['path'],
                    'alt_text' => $image['alt_text'] ?? null,
                    'sort_order' => $image['sort_order'] ?? 0,
                ]);
            }
        });

        return $studio->fresh(['images']);
    }

    public function listForCustomer(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->studioRepository->listForCustomer($filters, $perPage);
    }

    public function orderedForCustomer(array $filters = []): Collection
    {
        return $this->studioRepository->orderedForCustomer($filters);
    }

    /**
     * Studios in $orderedIds get positions 1..n; studios left out keep their relative order after them.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(array $orderedIds): Collection
    {
        DB::transaction(function () use ($orderedIds) {
            $orderedIds = array_map('intval', array_values($orderedIds));

            $this->applyOrder([...$orderedIds, ...$this->orderedIdsExcept($orderedIds)]);
        });

        return Studio::query()
            ->with('images')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Puts the studio at $position (1-based, clamped) and shifts the others so positions stay 1..n.
     */
    protected function moveToPosition(int $studioId, int $position): void
    {
        $ids = $this->orderedIdsExcept([$studioId]);
        $position = max(1, min($position, count($ids) + 1));

        array_splice($ids, $position - 1, 0, [$studioId]);

        $this->applyOrder($ids);
    }

    /**
     * @param  list<int>  $excludedIds
     * @return list<int>
     */
    protected function orderedIdsExcept(array $excludedIds): array
    {
        return Studio::query()
            ->whereNotIn('id', $excludedIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $orderedIds
     */
    protected function applyOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            Studio::query()->whereKey($id)->where('sort_order', '!=', $index + 1)->update(['sort_order' => $index + 1]);
        }
    }

    /**
     * description, address and rules are NOT NULL columns but optional in the API.
     */
    protected function normalizeTextColumns(array $data): array
    {
        foreach (['description', 'address', 'rules'] as $column) {
            if (array_key_exists($column, $data) && $data[$column] === null) {
                $data[$column] = '';
            }
        }

        return $data;
    }

    /**
     * @param  list<string>  $imagePaths
     */
    protected function syncImages(int $studioId, array $imagePaths): void
    {
        $existing = StudioImage::query()->where('studio_id', $studioId)->get();

        foreach ($existing as $image) {
            Storage::disk('public')->delete($image->path);
        }

        StudioImage::query()->where('studio_id', $studioId)->delete();

        foreach (array_values($imagePaths) as $index => $path) {
            StudioImage::query()->create([
                'studio_id' => $studioId,
                'path' => $path,
                'sort_order' => $index,
            ]);
        }
    }
}
