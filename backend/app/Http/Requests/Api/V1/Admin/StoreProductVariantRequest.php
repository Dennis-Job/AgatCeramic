<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'unique:product_variants,sku'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'old_price' => ['nullable', 'numeric', 'gte:price', 'max:9999999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
