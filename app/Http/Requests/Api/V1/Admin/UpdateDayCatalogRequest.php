<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDayCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price_per_day' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
            'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:51200',
            'clear_cover' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:51200',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:51200',
            'clear_images' => 'sometimes|boolean',
        ];
    }
}
