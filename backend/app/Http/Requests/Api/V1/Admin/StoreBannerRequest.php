<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'link_label' => ['nullable', 'required_with:link_url', 'string', 'max:255'],
            'link_url' => ['nullable', 'required_with:link_label', 'url:http,https', 'max:2048'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
