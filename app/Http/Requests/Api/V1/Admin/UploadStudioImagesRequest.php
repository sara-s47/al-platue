<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadStudioImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => 'required|array|min:1',
            'images.*.path' => 'required|string',
            'images.*.alt_text' => 'nullable|string',
            'images.*.sort_order' => 'nullable|integer',
        ];
    }
}