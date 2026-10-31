<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Validation\ValidationException;

class ProductCollectionAssignmentValidator
{
    /** @param array<string, mixed> $attributes */
    public function validate(Category $category, ?Product $product, array $attributes): void
    {
        $collectionId = array_key_exists('collection_id', $attributes) ? $attributes['collection_id'] : $product?->collection_id;
        if ($collectionId === null) {
            return;
        }
        $brandId = array_key_exists('brand_id', $attributes) ? $attributes['brand_id'] : $product?->brand_id;
        $collection = ProductCollection::query()->whereKey($collectionId)->lockForUpdate()->first();
        if ($collection === null || ! is_int($brandId) || $brandId !== $collection->brand_id) {
            throw ValidationException::withMessages(['collection_id' => ['The selected collection must belong to the product brand. Clear the collection explicitly before changing brand.']]);
        }
        if (! $category->uses_collections) {
            throw ValidationException::withMessages(['collection_id' => ['The selected category does not support collections. Clear the collection explicitly before changing category.']]);
        }
        if (! $collection->is_active && $product?->collection_id !== $collection->id) {
            throw ValidationException::withMessages(['collection_id' => ['An inactive collection cannot be assigned to a product.']]);
        }
    }
}
