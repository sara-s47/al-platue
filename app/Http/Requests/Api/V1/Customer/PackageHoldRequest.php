<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class PackageHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => 'required|integer|exists:packages,id',
            'start_at' => 'required|date',
            'guest_count' => 'nullable|integer|min:1',
            'equipment' => 'nullable|array',
            'equipment.*.id' => 'required_with:equipment|integer|exists:equipment,id',
            'equipment.*.quantity' => 'required_with:equipment|integer|min:1',
            'hospitality' => 'nullable|array',
            'hospitality.*.id' => 'required_with:hospitality|integer|exists:hospitality_items,id',
            'hospitality.*.quantity' => 'required_with:hospitality|integer|min:1',
        ];
    }
}
