<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSliderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('sliders')->ignore($this->route('slider'))],
            'is_published' => ['sometimes', 'boolean'],
            'banner_ids' => ['sometimes', 'array', 'max:50'],
            'banner_ids.*' => ['required', 'integer', 'distinct', Rule::exists('banners', 'id')],
        ];
    }
}
