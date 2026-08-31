<?php

namespace App\Services\Auth;

use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class UserService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->userRepository->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->userRepository->findOrFail($id);
    }

    public function updateStatus(int $id, string $status): Model
    {
        return $this->userRepository->update($id, ['status' => $status]);
    }

    public function update(int $id, array $data): Model
    {
        return $this->userRepository->update($id, $data);
    }
}