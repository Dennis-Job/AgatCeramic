<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('product_groups', 'code')->ignore($this->route('product_group'))],
            'axis_attribute_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:20'],
            'axis_attribute_ids.*' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'product_ids' => ['sometimes', 'required', 'array', 'min:2', 'max:500'],
            'product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
        ];
    }
}
