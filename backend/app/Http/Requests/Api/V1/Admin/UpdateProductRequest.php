<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'brand_id' => ['sometimes', 'nullable', 'integer', 'exists:brands,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'sku' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'article_number' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('products', 'article_number')->ignore($this->route('product'))],
            'barcode' => ['sometimes', 'nullable', 'string', 'regex:/^(?:[0-9]{8}|[0-9]{12,14})$/', Rule::unique('products', 'barcode')->ignore($this->route('product'))],
            'unit' => ['sometimes', 'required', Rule::enum(ProductUnit::class)],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
            'old_price' => ['sometimes', 'nullable', 'numeric', 'max:9999999999.99'],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
            'is_on_sale' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $price = $this->input('price', $this->route('product')->price);
            $oldPrice = $this->input('old_price', $this->route('product')->old_price);
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
