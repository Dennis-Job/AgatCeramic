<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:brands,name'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:brands,slug'],
            'description' => ['nullable', 'string', 'max:10000'],
            'logo_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where('kind', 'image')],
            'document_ids' => ['sometimes', 'array', 'max:50'],
            'document_ids.*' => ['integer', 'distinct', Rule::exists('media', 'id')->where('kind', 'document')],
            'country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
