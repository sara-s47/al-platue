<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoyaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rule_type' => 'required|string',
            'points' => 'required|numeric|min:0',
            'value' => 'nullable|numeric|min:0',
            'min_redemption_points' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}