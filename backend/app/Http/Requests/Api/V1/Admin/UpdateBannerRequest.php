<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'image_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'link_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'link_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
