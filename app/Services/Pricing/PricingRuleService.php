<?php

namespace App\Services\Pricing;

use App\Enums\PricingRuleType;
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
        $existing = $this->pricingRuleRepository->findOrFail($id);
        $mergedType = $data['rule_type'] ?? ($existing->rule_type instanceof PricingRuleType
            ? $existing->rule_type->value
            : (string) $existing->rule_type);

        return $this->pricingRuleRepository->update($id, $this->normalizePayload($data, $mergedType));
    }

    public function delete(int $id): bool
    {
        return $this->pricingRuleRepository->delete($id);
    }

    /**
     * Map API aliases and clear fields that do not apply to the rule type.
     */
    protected function normalizePayload(array $data, ?string $ruleType = null): array
    {
        if (array_key_exists('hourly_rate', $data)) {
            $data['price_per_hour'] = $data['hourly_rate'];
            unset($data['hourly_rate']);
        }

        // start_date is an alias for specific_date (single day only — no end_date range).
        if (array_key_exists('start_date', $data) && ! array_key_exists('specific_date', $data)) {
            $data['specific_date'] = $data['start_date'];
        }

        unset($data['start_date'], $data['end_date']);

        $type = $ruleType ?? ($data['rule_type'] ?? null);
        if ($type instanceof PricingRuleType) {
            $type = $type->value;
        }

        if ($type && $type !== PricingRuleType::DateSpecific->value) {
            $data['specific_date'] = null;
        }

        if ($type === PricingRuleType::Base->value) {
            $data['day_of_week'] = $data['day_of_week'] ?? null;
            $data['start_time'] = $data['start_time'] ?? null;
            $data['end_time'] = $data['end_time'] ?? null;
        }

        if ($type === PricingRuleType::DateSpecific->value) {
            $data['day_of_week'] = null;
            $data['start_time'] = null;
            $data['end_time'] = null;
        }

        return $data;
    }
}
