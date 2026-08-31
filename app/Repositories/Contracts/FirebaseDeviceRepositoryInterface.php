<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface FirebaseDeviceRepositoryInterface extends BaseRepositoryInterface
{
    public function findByUserAndToken(int $userId, string $deviceToken): ?Model;

    /**
     * @return array<int, string>
     */
    public function getActiveTokensForUser(int $userId): array;

    /**
     * @return array<int, string>
     */
    public function getActiveTokensForUsers(array $userIds): array;
}
