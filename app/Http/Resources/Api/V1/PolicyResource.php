<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'hours_before' => $this->hours_before ?? null,
            'min_notice_hours' => $this->min_notice_hours ?? null,
            'max_reschedules' => $this->max_reschedules ?? null,
            'refund_percentage' => $this->refund_percentage ?? null,
            'cancellation_fee' => $this->cancellation_fee ?? null,
            'reschedule_fee' => $this->fee ?? $this->reschedule_fee ?? null,
            'is_active' => $this->is_active,
        ];
    }
}
