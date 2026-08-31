<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_id' => 'required|integer|exists:bookings,id',
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'dimensions' => 'nullable|array',
        ];
    }
}