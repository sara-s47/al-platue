<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'points' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:255',
            'segment_id' => 'nullable|integer|exists:segments,id',
        ];
    }
}