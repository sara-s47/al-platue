<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => 'required|integer|min:1',
            'meta' => 'nullable|array',
            'meta.booking_mode' => 'nullable|in:hourly,daily',
            'meta.guest_count' => 'nullable|integer|min:1',
            'meta.package_id' => 'nullable|integer|min:1',
            'meta.paint_color_id' => 'nullable|integer|min:1',
            'meta.start_at' => 'nullable|date',
            'meta.end_at' => 'nullable|date|after:meta.start_at',
            'meta.start_date' => 'nullable|date_format:Y-m-d',
            'meta.end_date' => 'nullable|date_format:Y-m-d|after_or_equal:meta.start_date',
        ];
    }
}
