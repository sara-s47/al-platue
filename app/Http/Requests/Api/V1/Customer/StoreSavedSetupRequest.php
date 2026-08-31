<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavedSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'studio_id' => 'required|integer|exists:studios,id',
            'duration_minutes' => 'required|integer|min:1',
            'guest_count' => 'required|integer|min:1',
            'configuration_json' => 'nullable|array',
        ];
    }
}