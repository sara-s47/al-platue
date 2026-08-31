<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'image_path' => 'required|string',
            'link_url' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ];
    }
}