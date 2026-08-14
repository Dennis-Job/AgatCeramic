<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'is_active' => ['nullable', 'boolean'],
            'has_stock' => ['nullable', 'boolean'],
            'price_from' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'price_to' => ['nullable', 'numeric', 'gte:price_from', 'max:9999999999.99'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
