<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ProductUnit;
use App\Http\Requests\Api\V1\Admin\Concerns\HasProductVariantAttributeValueRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    use HasProductVariantAttributeValueRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...[
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'unique:product_variants,sku'],
            'article_number' => ['nullable', 'string', 'max:100', 'unique:product_variants,article_number'],
            'barcode' => ['nullable', 'string', 'regex:/^(?:[0-9]{8}|[0-9]{12,14})$/', 'unique:product_variants,barcode'],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'old_price' => ['nullable', 'numeric', 'gte:price', 'max:9999999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ], ...$this->productVariantAttributeValueRules()];
    }

    public function after(): array
    {
        return $this->productVariantAttributeValueValidation();
    }

    protected function prepareForValidation(): void
    {
        foreach (['article_number', 'barcode'] as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
