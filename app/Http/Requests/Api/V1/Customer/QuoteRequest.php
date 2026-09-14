<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\BookingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mode = $this->input('booking_mode', BookingMode::Hourly->value);

        return [
            'booking_mode' => ['nullable', 'string', Rule::enum(BookingMode::class)],
            'studio_id' => 'required|integer|exists:studios,id',
            'start_at' => [
                Rule::requiredIf($mode !== BookingMode::Daily->value),
                'nullable',
                'date',
            ],
            'end_at' => [
                Rule::requiredIf($mode !== BookingMode::Daily->value),
                'nullable',
                'date',
                'after:start_at',
            ],
            'start_date' => [
                Rule::requiredIf($mode === BookingMode::Daily->value),
                'nullable',
                'date',
            ],
            'end_date' => [
                Rule::requiredIf($mode === BookingMode::Daily->value),
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'guest_count' => 'required|integer|min:1',
            'package_id' => 'nullable|integer|exists:packages,id',
            'promo_code' => 'nullable|string|max:50',
            'points_to_redeem' => 'nullable|integer|min:0',
            'equipment' => 'nullable|array',
            'hospitality' => 'nullable|array',
        ];
    }
}
