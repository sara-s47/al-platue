<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RentalOrderQuoteRequest extends FormRequest
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
            'from_cart' => 'sometimes|boolean',
            'items' => 'required_without:from_cart|array|min:1',
            'items.*.type' => 'required_with:items|in:equipment,accessory',
            'items.*.id' => 'required_with:items|integer|min:1',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ];
    }
}
