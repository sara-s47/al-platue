<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Hourly: start_at + end_at. Daily: start_date + end_date (or start_at/end_at datetimes).
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
        ];
    }
}
