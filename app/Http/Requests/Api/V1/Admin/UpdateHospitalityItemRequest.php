<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\HospitalityPricingModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHospitalityItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'sometimes|integer|exists:hospitality_categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'pricing_model' => ['sometimes', 'string', Rule::enum(HospitalityPricingModel::class)],
            'price' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
        ];
    }
}
