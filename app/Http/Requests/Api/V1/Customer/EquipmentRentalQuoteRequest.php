<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class EquipmentRentalQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:equipment,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
