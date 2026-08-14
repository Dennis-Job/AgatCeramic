<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
            'alt' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
