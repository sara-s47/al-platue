<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_type' => $this->rule_type,
            'points' => $this->points,
            'value' => $this->value,
            'min_redemption_points' => $this->min_redemption_points,
            'max_redemption_amount' => $this->max_redemption_amount,
            'max_redemption_percentage' => $this->max_redemption_percentage,
            'is_active' => $this->is_active,
        ];
    }
}