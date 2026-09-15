<?php

namespace App\Services\Studio;

use App\Models\Studio;
use App\Models\StudioImage;
use App\Repositories\Contracts\StudioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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

    public function create(array $data): Studio
    {
        $weeklySchedule = $data['weekly_schedule'] ?? null;
        unset($data['weekly_schedule']);

        return DB::transaction(function () use ($data, $weeklySchedule) {
            $studio = $this->studioRepository->create($data);

            if (is_array($weeklySchedule) && $weeklySchedule !== []) {
                $this->scheduleService->setWeeklySchedule($studio->id, $weeklySchedule);
            }

            return $studio->fresh();
        });
    }

    public function update(int $id, array $data): Studio
    {
        return $this->studioRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->studioRepository->delete($id);
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
}
