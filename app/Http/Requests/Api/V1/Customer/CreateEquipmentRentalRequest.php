<?php

namespace App\Http\Requests\Api\V1\Customer;

class CreateEquipmentRentalRequest extends EquipmentRentalQuoteRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'customer_notes' => 'nullable|string|max:1000',
        ]);
    }
}
