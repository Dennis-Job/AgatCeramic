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

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Product $product */
            $product = $this->route('product');
            $this->validateCategoryAttributeValues($validator, $product, $this->input('attributes', []), 'attributes', $product->is_active);
        }];
    }
}
