<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($this->route('brand'))],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($this->route('brand'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'logo_id' => ['sometimes', 'nullable', 'integer', Rule::exists('media', 'id')->where('kind', 'image')],
            'document_ids' => ['sometimes', 'array', 'max:50'],
            'document_ids.*' => ['integer', 'distinct', Rule::exists('media', 'id')->where('kind', 'document')],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
