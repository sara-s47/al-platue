<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Concerns\NormalizesUrls;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInteriorDesignerRequest extends FormRequest
{
    use NormalizesUrls;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeUrls(['portfolio_url']);
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:51200',
            'remove_image' => 'sometimes|boolean',
            'portfolio_url' => 'nullable|url|max:500',
            'notes' => 'nullable|array|max:50',
            'notes.*' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ];
    }
}
