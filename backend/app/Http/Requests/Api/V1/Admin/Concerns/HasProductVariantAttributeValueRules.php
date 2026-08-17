<?php

namespace App\Http\Requests\Api\V1\Admin\Concerns;

use App\Models\Product;
use Illuminate\Validation\Validator;

trait HasProductVariantAttributeValueRules
{
    use ValidatesCategoryAttributeValues;

    /** @return array<string, list<mixed>> */
    protected function productVariantAttributeValueRules(): array
    {
        return [
            'attribute_values' => ['sometimes', 'array', 'max:500'],
            'attribute_values.*' => ['required', 'array:attribute_id,value'],
            'attribute_values.*.attribute_id' => ['required', 'integer', 'distinct', 'exists:attributes,id'],
            'attribute_values.*.value' => ['present'],
        ];
    }

    /** @return array<int, callable> */
    protected function productVariantAttributeValueValidation(): array
    {
        return [function (Validator $validator): void {
            if (! $this->has('attribute_values')) {
                return;
            }

            /** @var Product $product */
            $product = $this->route('product');
            $this->validateCategoryAttributeValues($validator, $product, $this->input('attribute_values', []), 'attribute_values', false);
        }];
    }
}
