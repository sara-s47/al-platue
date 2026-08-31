<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SetWeeklyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => 'required|array|min:1',
            'days.*.day_of_week' => 'required|integer|between:0,6',
            'days.*.open_time' => 'nullable|date_format:H:i',
            'days.*.close_time' => 'nullable|date_format:H:i',
            'days.*.is_closed' => 'boolean',
        ];
    }
}