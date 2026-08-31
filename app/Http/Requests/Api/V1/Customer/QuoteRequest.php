<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'studio_id' => 'required|integer|exists:studios,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'guest_count' => 'required|integer|min:1',
            'package_id' => 'nullable|integer|exists:packages,id',
            'promo_code' => 'nullable|string|max:50',
            'points_to_redeem' => 'nullable|integer|min:0',
            'equipment' => 'nullable|array',
            'hospitality' => 'nullable|array',
        ];
    }
}