<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
            'type' => 'required|string|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after:valid_from',
            'minimum_booking_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'studio_ids' => 'nullable|array',
        ];
    }
}