<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricingRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'studio_id' => $this->studio_id,
            'rule_type' => $this->rule_type,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'specific_date' => $this->specific_date,
            'price_per_hour' => $this->price_per_hour,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
        ];
    }
}
