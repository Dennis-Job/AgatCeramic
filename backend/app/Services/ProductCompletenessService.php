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

        $requiredIds = $product->category->attributes()->wherePivot('is_required', true)->pluck('attributes.id');
        $valueIds = $product->attributeValues()->pluck('attribute_id');
        if ($requiredIds->diff($valueIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['is_active' => ['The product cannot be activated until all required category attributes are filled.']]);
        }
    }
}
