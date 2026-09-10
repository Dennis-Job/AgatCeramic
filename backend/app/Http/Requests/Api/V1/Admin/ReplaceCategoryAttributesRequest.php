<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceCategoryAttributesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'attributes' => ['present', 'array', 'max:500'],
            'attributes.*.id' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'attributes.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'attributes.*.is_required' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, array{id: int, sort_order?: int, is_required?: bool}> */
    public function attributes(): array
    {
        $attributes = [];
        foreach (array_keys($this->array('attributes')) as $index) {
            $item = ['id' => $this->integer("attributes.{$index}.id")];
            if ($this->has("attributes.{$index}.sort_order")) {
                $item['sort_order'] = $this->integer("attributes.{$index}.sort_order");
            }
            if ($this->has("attributes.{$index}.is_required")) {
                $item['is_required'] = $this->boolean("attributes.{$index}.is_required");
            }
            $attributes[] = $item;
        }

        return $attributes;
    }
}
