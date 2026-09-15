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
            'studio_name' => $this->when(
                $this->relationLoaded('studio'),
                fn () => $this->studio?->name,
            ),
            'rule_type' => $this->rule_type,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'specific_date' => $specificDate,
            'price_per_hour' => $this->price_per_hour,
            'hourly_rate' => $this->price_per_hour,
            'price_per_day' => $this->price_per_day,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
        ];
    }
}
