<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\EquipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('studios'))) {
            $this->merge(['studios' => json_decode($this->input('studios'), true) ?? $this->input('studios')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'sometimes|integer|min:1',
            'price_per_hour' => 'sometimes|numeric|min:0',
            'price_per_day' => 'sometimes|numeric|min:0',
            'status' => ['nullable', 'string', Rule::in([...array_column(EquipmentStatus::cases(), 'value'), 'available'])],
            'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'clear_cover' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
            'clear_images' => 'sometimes|boolean',
            'studios' => 'sometimes|nullable|array',
            'studios.*.id' => 'required|integer|distinct|exists:studios,id',
            'studios.*.quantity' => 'nullable|integer|min:1',
            'clear_studios' => 'sometimes|boolean',
        ];
    }
}
