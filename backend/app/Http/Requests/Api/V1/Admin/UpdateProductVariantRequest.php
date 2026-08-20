<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ProductUnit;
use App\Http\Requests\Api\V1\Admin\Concerns\HasProductVariantAttributeValueRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariantRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('product_variants', 'sku')->ignore($this->route('variant'))],
            'article_number' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('product_variants', 'article_number')->ignore($this->route('variant'))],
            'barcode' => ['sometimes', 'nullable', 'string', 'regex:/^(?:[0-9]{8}|[0-9]{12,14})$/', Rule::unique('product_variants', 'barcode')->ignore($this->route('variant'))],
            'unit' => ['sometimes', 'required', Rule::enum(ProductUnit::class)],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
            'old_price' => ['sometimes', 'nullable', 'numeric', 'max:9999999999.99'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ], ...$this->productVariantAttributeValueRules()];
    }

    public function after(): array
    {
        return [...$this->productVariantAttributeValueValidation(), function ($validator): void {
            $price = $this->input('price', $this->route('variant')->price);
            $oldPrice = $this->input('old_price', $this->route('variant')->old_price);

            if ($oldPrice !== null && (float) $oldPrice < (float) $price) {
                $validator->errors()->add('old_price', 'The old price must be greater than or equal to the price.');
            }
        }];
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
