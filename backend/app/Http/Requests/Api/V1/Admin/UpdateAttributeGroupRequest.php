<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttributeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('attribute_groups', 'name')->ignore($this->route('attribute_group'))], 'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('attribute_groups', 'slug')->ignore($this->route('attribute_group'))], 'description' => ['sometimes', 'nullable', 'string', 'max:10000'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647']];
    }
}
