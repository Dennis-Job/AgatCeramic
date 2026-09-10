<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class ProductCompletenessService
{
    public function assertCanActivate(Product $product): void
    {
        foreach (['sku', 'unit', 'price', 'stock_quantity'] as $field) {
            if ($product->{$field} === null || $product->{$field} === '') {
                throw ValidationException::withMessages(['is_active' => ["The product cannot be activated until {$field} is filled."]]);
            }
        }

        $category = $product->category;
        if ($category === null) {
            throw ValidationException::withMessages(['category_id' => ['The product category is not available.']]);
        }
        $requiredIds = $category->attributes()->wherePivot('is_required', true)->pluck('attributes.id');
        $valueIds = $product->attributeValues()->pluck('attribute_id');
        if ($requiredIds->diff($valueIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['is_active' => ['The product cannot be activated until all required category attributes are filled.']]);
        }
    }
}
