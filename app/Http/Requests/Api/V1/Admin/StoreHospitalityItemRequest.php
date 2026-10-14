<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\HospitalityPricingModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHospitalityItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('studios')) {
            $this->merge(['studios' => self::normalizeStudioIds($this->input('studios'))]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|integer|exists:hospitality_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'pricing_model' => ['required', 'string', Rule::enum(HospitalityPricingModel::class)],
            'price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'studios' => 'sometimes|nullable|array',
            'studios.*' => 'integer|distinct|exists:studios,id',
        ];
    }

    /**
     * Accepts [1, 2], [{"id": 1}, {"id": 2}] or the same as a JSON string (FormData).
     */
    public static function normalizeStudioIds(mixed $studios): mixed
    {
        if (is_string($studios)) {
            $studios = json_decode($studios, true) ?? $studios;
        }

        if (! is_array($studios)) {
            return $studios;
        }

        return array_map(fn ($studio) => is_array($studio) ? ($studio['id'] ?? null) : $studio, array_values($studios));
    }
}