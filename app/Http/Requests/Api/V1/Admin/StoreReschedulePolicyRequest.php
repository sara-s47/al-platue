<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreReschedulePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'min_notice_hours' => 'required|integer|min:0',
            'reschedule_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}