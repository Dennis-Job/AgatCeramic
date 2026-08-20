<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\Concerns\ValidatesAttributeOptions;
use App\Models\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAttributeRequest extends FormRequest
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
            'attribute_group_id' => ['sometimes', 'nullable', 'integer', 'exists:attribute_groups,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('attributes', 'name')->ignore($this->route('attribute'))],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('attributes', 'slug')->ignore($this->route('attribute'))],
            'type' => ['sometimes', 'required', 'string', Rule::in(Attribute::TYPES)],
            'unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_required' => ['sometimes', 'boolean'],
            'is_visible_on_product_page' => ['sometimes', 'boolean'],
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
