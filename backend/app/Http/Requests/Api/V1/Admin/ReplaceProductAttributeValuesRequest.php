<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\Concerns\ValidatesCategoryAttributeValues;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReplaceProductAttributeValuesRequest extends FormRequest
{
    use ValidatesCategoryAttributeValues;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'attributes' => ['present', 'array', 'max:500'],
            'attributes.*' => ['required', 'array:attribute_id,value'],
            'attributes.*.attribute_id' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'attributes.*.value' => ['present'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Product $product */
            $product = $this->route('product');
            $values = [];
            foreach ($this->array('attributes') as $value) {
                if (is_array($value) && array_key_exists('attribute_id', $value) && array_key_exists('value', $value)) {
                    $values[] = ['attribute_id' => $value['attribute_id'], 'value' => $value['value']];
                }
            }
            $this->validateCategoryAttributeValues($validator, $product, $values, 'attributes', $product->is_active);
        }];
    }

    /** @return array<int, array{attribute_id: int, value: mixed}> */
    public function productAttributeValues(): array
    {
        $attributes = [];
        foreach (array_keys($this->array('attributes')) as $index) {
            $attributes[] = [
                'attribute_id' => $this->integer("attributes.{$index}.attribute_id"),
                'value' => $this->input("attributes.{$index}.value"),
            ];
        }

        return $attributes;
    }
}
