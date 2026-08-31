<?php

namespace App\Services\Pricing;

use App\Repositories\Contracts\PricingRuleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class PricingRuleService
{
    public function __construct(
        protected PricingRuleRepositoryInterface $pricingRuleRepository,
    ) {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->pricingRuleRepository->paginate($perPage);
    }

    public function findOrFail(int $id): Model
    {
        return $this->pricingRuleRepository->findOrFail($id);
    }

    public function create(array $data): Model
    {
        return $this->pricingRuleRepository->create($data);
    }

    public function update(int $id, array $data): Model
    {
        return $this->pricingRuleRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->pricingRuleRepository->delete($id);
    }
}