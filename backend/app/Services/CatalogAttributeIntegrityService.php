<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class CatalogAttributeIntegrityService
{
    /** @param array<int, int> $attributeIds */
    public function assertCategoryAssignmentsCanBeReplaced(Category $category, array $attributeIds, string $field = 'attributes'): void
    {
        $assignedIds = $category->attributes()->pluck('attributes.id')->map(static fn (mixed $id): int => (int) $id);
        $replacementIds = collect($attributeIds)->map(static fn (mixed $id): int => (int) $id)->unique();
        $removedIds = $assignedIds->diff($replacementIds);

        if ($removedIds->isNotEmpty() && $this->categoryUsesAnyAttribute($category, $removedIds->all())) {
            throw ValidationException::withMessages([
                $field => ['Cannot detach attributes that are used by products or variants in this category.'],
            ]);
        }

    }

    public function assertProductCanUseCategory(Product $product, Category $category): void
    {
        $assignedIds = $category->attributes()->pluck('attributes.id')->map(static fn (mixed $id): int => (int) $id);
        $usedIds = $product->attributeValues()->pluck('attribute_id')
            ->merge($product->variants()->join(
                'product_variant_attribute_values',
                'product_variants.id',
                '=',
                'product_variant_attribute_values.product_variant_id',
            )->pluck('product_variant_attribute_values.attribute_id'))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique();

        if ($usedIds->diff($assignedIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'category_id' => ['The selected category does not contain all attributes currently used by the product or its variants.'],
            ]);
        }

        $requiredIds = $category->attributes()->where('is_required', true)->pluck('attributes.id');
        $productValueIds = $product->attributeValues()->pluck('attribute_id');

        if ($requiredIds->diff($productValueIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'category_id' => ['The selected category requires attributes that are missing from the product.'],
            ]);
        }
    }

    /** @param array<int, int> $attributeIds */
    public function assertValuesBelongToCategory(Product $product, array $attributeIds, string $field, bool $requireAll): void
    {
        $assigned = $product->category->attributes()->get(['attributes.id', 'attributes.name', 'attributes.is_required']);
        $submittedIds = collect($attributeIds)->map(static fn (mixed $id): int => (int) $id)->unique();

        if ($submittedIds->diff($assigned->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                $field => ['One or more attributes are not assigned to the product category.'],
            ]);
        }

        if ($requireAll) {
            $missing = $assigned->where('is_required', true)->pluck('id')->diff($submittedIds);
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    $field => ['One or more required category attributes are missing.'],
                ]);
            }
        }
    }

    /** @param array<int, int> $attributeIds */
    private function categoryUsesAnyAttribute(Category $category, array $attributeIds): bool
    {
        return $category->products()->whereHas(
            'attributeValues',
            fn ($query) => $query->whereIn('attribute_id', $attributeIds),
        )->exists() || $category->products()->whereHas(
            'variants.attributeValues',
            fn ($query) => $query->whereIn('attribute_id', $attributeIds),
        )->exists();
    }
}
