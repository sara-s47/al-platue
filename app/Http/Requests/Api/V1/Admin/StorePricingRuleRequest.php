<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\PricingRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePricingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'studio_id' => 'required|integer|exists:studios,id',
            'rule_type' => ['required', 'string', Rule::enum(PricingRuleType::class)],
            'price_per_hour' => 'required_without:hourly_rate|numeric|min:0',
            'hourly_rate' => 'required_without:price_per_hour|numeric|min:0',
            'price_per_day' => 'nullable|numeric|min:0',
            'day_of_week' => 'nullable|integer|between:0,6',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'specific_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'prohibited',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
