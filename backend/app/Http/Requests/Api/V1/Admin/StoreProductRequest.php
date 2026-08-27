<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:products,slug'],
            'description' => ['nullable', 'string', 'max:10000'],
            'sku' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'unique:products,sku'],
            'article_number' => ['nullable', 'string', 'max:100', 'unique:products,article_number'],
            'barcode' => ['nullable', 'string', 'regex:/^(?:[0-9]{8}|[0-9]{12,14})$/', 'unique:products,barcode'],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'old_price' => ['nullable', 'numeric', 'gte:price', 'max:9999999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => false]);
        }
        foreach (['article_number', 'barcode'] as $field) {
            if ($this->has($field)) {
                $value = trim((string) $this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
