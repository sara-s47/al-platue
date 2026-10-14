<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\LoyaltyRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoyaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'rule_type' => ['required', 'string', Rule::enum(LoyaltyRuleType::class)],
            'points' => 'required|numeric|min:0',
            'value' => [
                'nullable',
                'numeric',
                Rule::when($this->input('rule_type') === LoyaltyRuleType::Redemption->value, ['gt:0'], ['min:0']),
            ],
            'min_redemption_points' => 'nullable|integer|min:0',
            'max_redemption_amount' => 'nullable|numeric|min:0',
            'max_redemption_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ];
    }
}