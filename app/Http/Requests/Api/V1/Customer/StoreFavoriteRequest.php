<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\Favorite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('type') && $this->filled('studio_id')) {
            $this->merge([
                'type' => Favorite::TYPE_STUDIO,
                'id' => $this->input('studio_id'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Favorite::TYPES)],
            'id' => 'required|integer|min:1',
            'studio_id' => 'nullable|integer|exists:studios,id',
        ];
    }
}
