<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReorderStudiosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'integer|distinct|exists:studios,id',
        ];
    }
}
