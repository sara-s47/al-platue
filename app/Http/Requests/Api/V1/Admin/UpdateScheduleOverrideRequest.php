<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Concerns\NormalizesClockTimes;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleOverrideRequest extends FormRequest
{
    use NormalizesClockTimes;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeClockTimes(['open_time', 'close_time']);
    }

    public function rules(): array
    {
        return [
            'date' => 'sometimes|date',
            'is_closed' => 'boolean',
            'open_time' => 'nullable|date_format:H:i',
            'close_time' => 'nullable|date_format:H:i',
            'reason' => 'nullable|string|max:255',
        ];
    }
}
