<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavedSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'duration_minutes' => 'sometimes|integer|min:1',
            'guest_count' => 'sometimes|integer|min:1',
            'configuration_json' => 'nullable|array',
        ];
    }
}