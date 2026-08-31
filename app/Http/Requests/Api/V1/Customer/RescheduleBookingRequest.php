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
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'reason' => 'nullable|string|max:500',
        ];
    }
}