<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CreateHoldRequest extends FormRequest
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
            'guest_count' => 'nullable|integer|min:1',
        ];
    }
}