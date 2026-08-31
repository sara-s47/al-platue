<?php

namespace App\Repositories\Eloquent;

use App\Models\FirebaseDevice;
use App\Repositories\Contracts\FirebaseDeviceRepositoryInterface;

class EloquentFirebaseDeviceRepository extends BaseRepository implements FirebaseDeviceRepositoryInterface
{
    public function __construct(FirebaseDevice $model)
    {
        parent::__construct($model);
    }

    public function findByUserAndToken(int $userId, string $deviceToken): ?FirebaseDevice
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('device_token', $deviceToken)
            ->first();
    }

    public function getActiveTokensForUser(int $userId): array
    {
        return $this->model->newQuery()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('device_token')
            ->all();
    }

    public function getActiveTokensForUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->model->newQuery()
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->pluck('device_token')
            ->all();
    }
}
