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
        $type = $this->input('type');

        $rules = [
            'type' => ['required', 'string', Rule::in(CartItem::TYPES)],
            'id' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1',
            'meta' => 'nullable|array',
        ];

        if ($type === CartItem::TYPE_STUDIO) {
            $rules['quantity'] = 'nullable|integer|min:1';
            $rules['meta.booking_mode'] = 'required|in:hourly,daily';
            $rules['meta.guest_count'] = 'nullable|integer|min:1';
            $rules['meta.package_id'] = 'nullable|integer|min:1';
            $rules['meta.paint_color_id'] = 'nullable|integer|min:1';
            $rules['meta.start_at'] = 'required_if:meta.booking_mode,hourly|nullable|date';
            $rules['meta.end_at'] = 'required_if:meta.booking_mode,hourly|nullable|date|after:meta.start_at';
            $rules['meta.start_date'] = 'required_if:meta.booking_mode,daily|nullable|date_format:Y-m-d';
            $rules['meta.end_date'] = 'required_if:meta.booking_mode,daily|nullable|date_format:Y-m-d|after_or_equal:meta.start_date';
        }

        return $rules;
    }
}
