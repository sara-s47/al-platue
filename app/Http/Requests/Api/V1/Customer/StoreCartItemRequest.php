<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\CartItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(CartItem::TYPES)],
            'id' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
