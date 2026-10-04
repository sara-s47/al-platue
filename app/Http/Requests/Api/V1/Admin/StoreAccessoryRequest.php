<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccessoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_per_day' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
        ];
    }
}
