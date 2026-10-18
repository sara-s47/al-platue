<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudioItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('colors'))) {
            $decoded = json_decode($this->input('colors'), true);
            if (is_array($decoded)) {
                $this->merge(['colors' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'clear_image' => 'sometimes|boolean',
            'colors' => 'nullable|array',
            'colors.*' => 'string|max:50',
            'default_quantity' => 'sometimes|integer|min:0',
            'min_quantity' => 'sometimes|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
