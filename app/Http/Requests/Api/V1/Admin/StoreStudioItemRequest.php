<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudioItemRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'colors' => 'nullable|array',
            'colors.*' => 'string|max:50',
            'default_quantity' => 'required|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $default = (int) $this->input('default_quantity', 0);
            $min = (int) $this->input('min_quantity', 0);
            if ($min > $default) {
                $validator->errors()->add('min_quantity', 'The min quantity must not exceed the default quantity.');
            }
        });
    }
}
