<?php

namespace App\Services\Loyalty;

use App\Repositories\Contracts\LoyaltyRuleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class LoyaltyRuleService
{
    public function __construct(
        protected LoyaltyRuleRepositoryInterface $loyaltyRuleRepository,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->loyaltyRuleRepository->paginate($perPage);
    }

    public function create(array $data): Model
    {
        return $this->loyaltyRuleRepository->create($data);
    }

    public function update(int $id, array $data): Model
    {
        return $this->loyaltyRuleRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->loyaltyRuleRepository->delete($id);
    }
}