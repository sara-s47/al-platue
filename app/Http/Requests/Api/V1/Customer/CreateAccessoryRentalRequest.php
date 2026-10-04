<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class CreateAccessoryRentalRequest extends AccessoryRentalQuoteRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'customer_notes' => 'nullable|string|max:1000',
        ]);
    }
}
