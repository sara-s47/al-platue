<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $specificDate = $this->specific_date;

        if ($specificDate !== null) {
            $specificDate = $this->specific_date instanceof \Carbon\CarbonInterface
                ? $this->specific_date->toDateString()
                : (string) $this->specific_date;
        }

        return [
            'id' => $this->id,
            'studio_id' => $this->studio_id,
            'rule_type' => $this->rule_type,
            'hourly_rate' => $this->price_per_hour,
            'start_date' => $specificDate,
            'end_date' => null,
            'is_active' => $this->is_active,
        ];
    }
}
