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
        return $this->pricingRuleRepository->create($this->normalizePayload($data));
    }

    public function update(int $id, array $data): Model
    {
        return $this->pricingRuleRepository->update($id, $this->normalizePayload($data));
    }

    public function delete(int $id): bool
    {
        return $this->pricingRuleRepository->delete($id);
    }

    /**
     * Map API aliases to DB columns.
     * hourly_rate → price_per_hour, start_date → specific_date
     */
    protected function normalizePayload(array $data): array
    {
        if (array_key_exists('hourly_rate', $data)) {
            $data['price_per_hour'] = $data['hourly_rate'];
            unset($data['hourly_rate']);
        }

        if (array_key_exists('start_date', $data) && ! array_key_exists('specific_date', $data)) {
            $data['specific_date'] = $data['start_date'];
        }

        unset($data['start_date'], $data['end_date']);

        return $data;
    }
}
