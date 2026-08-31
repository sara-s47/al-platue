<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'studio_id' => 'required|integer|exists:studios,id',
            'quantity' => 'required|integer|min:1',
        ];
    }
}