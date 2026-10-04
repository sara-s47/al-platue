<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\AccessoryRentalStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccessoryRentalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(AccessoryRentalStatus::class)],
        ];
    }
}
