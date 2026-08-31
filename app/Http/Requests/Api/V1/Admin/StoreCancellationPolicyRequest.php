<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCancellationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours_before' => 'required|integer|min:0',
            'refund_percentage' => 'required|numeric|min:0|max:100',
            'cancellation_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}