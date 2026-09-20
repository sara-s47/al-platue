<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Concerns\NormalizesClockTimes;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudioRequest extends FormRequest
{
    use NormalizesClockTimes;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeClockTimes([
            'weekly_schedule.*.open_time',
            'weekly_schedule.*.close_time',
        ]);
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|integer|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'rules' => 'nullable|string',
            'is_active' => 'boolean',
            'weekly_schedule' => 'nullable|array|min:1',
            'weekly_schedule.*.day_of_week' => 'required_with:weekly_schedule|integer|between:0,6',
            'weekly_schedule.*.open_time' => 'nullable|date_format:H:i',
            'weekly_schedule.*.close_time' => 'nullable|date_format:H:i',
            'weekly_schedule.*.is_closed' => 'boolean',
        ];
    }
}