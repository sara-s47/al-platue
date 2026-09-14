<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PricingRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'studio_id' => 'sometimes|integer|exists:studios,id',
            'rule_type' => ['sometimes', 'string', Rule::enum(PricingRuleType::class)],
            'price_per_hour' => 'sometimes|numeric|min:0',
            'hourly_rate' => 'sometimes|numeric|min:0',
            'price_per_day' => 'nullable|numeric|min:0',
            'day_of_week' => 'nullable|integer|between:0,6',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'specific_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
