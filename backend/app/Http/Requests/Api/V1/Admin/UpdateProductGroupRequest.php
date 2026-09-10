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

    /** @return array<string, list<mixed>> */
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

    /** @return array{name?: string, code?: string, axis_attribute_ids?: list<int>, product_ids?: list<int>} */
    public function payload(): array
    {
        $payload = [];
        if ($this->has('name')) {
            $payload['name'] = $this->string('name')->toString();
        }
        if ($this->has('code')) {
            $payload['code'] = $this->string('code')->toString();
        }
        if ($this->has('axis_attribute_ids')) {
            $payload['axis_attribute_ids'] = array_map(fn (int $index): int => $this->integer("axis_attribute_ids.{$index}"), array_keys($this->array('axis_attribute_ids')));
        }
        if ($this->has('product_ids')) {
            $payload['product_ids'] = array_map(fn (int $index): int => $this->integer("product_ids.{$index}"), array_keys($this->array('product_ids')));
        }

        return $payload;
    }
}
