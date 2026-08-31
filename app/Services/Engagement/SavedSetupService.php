<?php

namespace App\Services\Engagement;

use App\Exceptions\BusinessException;
use App\Repositories\Contracts\SavedSetupRepositoryInterface;
use App\Repositories\Contracts\StudioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class SavedSetupService
{
    public function __construct(
        protected SavedSetupRepositoryInterface $savedSetupRepository,
        protected StudioRepositoryInterface $studioRepository,
    ) {
    }

    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return \App\Models\SavedSetup::query()->where('user_id', $userId)->latest()->paginate($perPage);
    }

    public function create(int $userId, array $data): Model
    {
        $this->studioRepository->findOrFail((int) $data['studio_id']);

        return $this->savedSetupRepository->create(array_merge($data, ['user_id' => $userId]));
    }

    public function update(int $id, int $userId, array $data): Model
    {
        $setup = $this->savedSetupRepository->findOrFail($id);

        if ((int) $setup->user_id !== $userId) {
            throw new BusinessException('Saved setup does not belong to this user.', 'setup_unauthorized');
        }

        return $this->savedSetupRepository->update($id, $data);
    }

    public function delete(int $id, int $userId): bool
    {
        $setup = $this->savedSetupRepository->findOrFail($id);

        if ((int) $setup->user_id !== $userId) {
            throw new BusinessException('Saved setup does not belong to this user.', 'setup_unauthorized');
        }

        return $this->savedSetupRepository->delete($id);
    }
}