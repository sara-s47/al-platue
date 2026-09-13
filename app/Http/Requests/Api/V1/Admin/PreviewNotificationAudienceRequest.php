<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\NotificationAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewNotificationAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audience' => ['required', 'string', Rule::enum(NotificationAudience::class)],
            'user_ids' => [
                'nullable',
                'array',
                Rule::requiredIf(fn () => $this->input('audience') === NotificationAudience::Custom->value),
                Rule::when(
                    $this->input('audience') === NotificationAudience::Custom->value,
                    ['min:1'],
                ),
            ],
            'user_ids.*' => 'integer|exists:users,id',
        ];
    }
}
