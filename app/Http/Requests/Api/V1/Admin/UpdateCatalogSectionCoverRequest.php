<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\CatalogSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatalogSectionCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => ['required', 'string', Rule::in(CatalogSection::values())],
            'cover_image' => 'required_without:clear_cover|nullable|image|mimes:jpeg,jpg,png,webp|max:51200',
            'clear_cover' => 'sometimes|boolean',
        ];
    }
}
