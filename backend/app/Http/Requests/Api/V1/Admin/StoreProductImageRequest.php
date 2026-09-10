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
            'is_primary' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    /** @return array<string, mixed> */
    public function imageAttributes(): array
    {
        $attributes = [];
        if ($this->has('is_primary')) {
            $attributes['is_primary'] = $this->boolean('is_primary');
        }
        if ($this->has('sort_order')) {
            $attributes['sort_order'] = $this->integer('sort_order');
        }

        return $attributes;
    }
}
