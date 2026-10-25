<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'sometimes|integer|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'sometimes|integer|min:1',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'rules' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'sometimes|integer|min:1',
            'paint_pricing_mode' => 'sometimes|in:flat_extra,per_color',
            'flat_extra_paint_price' => 'nullable|numeric|min:0',
            'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'clear_cover' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
            'clear_images' => 'sometimes|boolean',
        ];
    }
}