<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Models\CartItem;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hasStudio = CartItem::query()
            ->where('user_id', $this->user()?->id)
            ->where('item_type', CartItem::TYPE_STUDIO)
            ->exists();

        return [
            'start_date' => ($hasStudio ? 'nullable' : 'required').'|date_format:Y-m-d|after_or_equal:today',
            'end_date' => ($hasStudio ? 'nullable' : 'required').'|date_format:Y-m-d|after_or_equal:start_date',
            'customer_notes' => 'nullable|string|max:1000',
        ];
    }
}
