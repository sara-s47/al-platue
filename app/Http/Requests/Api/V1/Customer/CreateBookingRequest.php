<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hold_id' => 'required|integer|exists:booking_holds,id',
            'guest_count' => 'required|integer|min:1',
            'package_id' => 'nullable|integer|exists:packages,id',
            'promo_code' => 'nullable|string|max:50',
            'points_to_redeem' => 'nullable|integer|min:0',
            'customer_notes' => 'nullable|string|max:1000',
            'equipment' => 'nullable|array',
            'hospitality' => 'nullable|array',
        ];
    }
}