<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
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
            'sku' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('product_variants', 'sku')->ignore($this->route('variant'))],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
            'old_price' => ['sometimes', 'nullable', 'numeric', 'max:9999999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $price = $this->input('price', $this->route('variant')->price);
            $oldPrice = $this->input('old_price', $this->route('variant')->old_price);

            if ($oldPrice !== null && (float) $oldPrice < (float) $price) {
                $validator->errors()->add('old_price', 'The old price must be greater than or equal to the price.');
            }
        }];
    }
}
