<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudioBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date|after:start_at',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
