<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\Concerns\ValidatesAttributeOptions;
use App\Models\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttributeRequest extends FormRequest
{
    use ValidatesAttributeOptions;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'attribute_group_id' => ['nullable', 'integer', 'exists:attribute_groups,id'],
            'name' => ['required', 'string', 'max:255', 'unique:attributes,name'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:attributes,slug'],
            'type' => ['required', 'string', Rule::in(Attribute::TYPES)],
            'unit' => ['nullable', 'string', 'max:64'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'options' => ['required_if:type,select,multiselect', 'array', 'max:500'],
            'options.*.value' => ['required', 'string', 'max:255', 'distinct'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateOptionsForType($validator)];
    }
}
