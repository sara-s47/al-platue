<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\EquipmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
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
            'quantity' => 'required|integer|min:1',
            'price_per_hour' => 'required|numeric|min:0',
            'status' => ['nullable', 'string', Rule::in([...array_column(EquipmentStatus::cases(), 'value'), 'available'])],
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
        ];
    }
}
