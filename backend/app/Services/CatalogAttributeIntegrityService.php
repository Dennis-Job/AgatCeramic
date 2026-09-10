<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CatalogAttributeIntegrityService
{
    public function __construct(private readonly AttributeValueValidator $valueValidator) {}

    /** @param array<int, int> $attributeIds */
    public function assertCategoryAssignmentsCanBeReplaced(Category $category, array $attributeIds, string $field = 'attributes'): void
    {
        $assignedIds = collect($this->integerIds($category->attributes()->pluck('attributes.id')->all()));
        $replacementIds = collect($attributeIds)->unique();
        $removedIds = $assignedIds->diff($replacementIds);

        if ($removedIds->isNotEmpty() && $this->categoryUsesAnyAttribute($category, $removedIds->all())) {
            throw ValidationException::withMessages([
                $field => ['Cannot detach attributes that are used by products or variants in this category.'],
            ]);
        }
    }

    public function assertProductCanUseCategory(Product $product, Category $category): void
    {
        $assignedIds = collect($this->integerIds($category->attributes()->pluck('attributes.id')->all()));
        $usedIds = $product->attributeValues()->pluck('attribute_id');
        if (Schema::hasTable('product_variant_attribute_values')) {
            $usedIds = $usedIds->merge($product->variants()->join(
                'product_variant_attribute_values',
                'product_variants.id',
                '=',
                'product_variant_attribute_values.product_variant_id',
            )->pluck('product_variant_attribute_values.attribute_id'));
        }
        $usedIds = collect($this->integerIds($usedIds->all()))->unique();

        if ($usedIds->diff($assignedIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'category_id' => ['The selected category does not contain all attributes currently used by the product or its variants.'],
            ]);
        }

        $requiredIds = collect($this->integerIds($category->attributes()->wherePivot('is_required', true)->pluck('attributes.id')->all()));
        $productValueIds = collect($this->integerIds($product->attributeValues()->pluck('attribute_id')->all()));

        if ($product->is_active && $requiredIds->diff($productValueIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'category_id' => ['The selected category requires attributes that are missing from the product.'],
            ]);
        }
    }

    /** @param array<int, array{attribute_id: int, value: mixed}> $values */
    public function assertValuesMatchCategory(Product $product, array $values, string $field, bool $requireAll): void
    {
        $category = $product->category;
        if ($category === null) {
            throw ValidationException::withMessages([$field => ['The product category no longer exists.']]);
        }
        $assigned = $category->attributes()->with('options')->get()->keyBy('id');
        $submittedIds = collect($values)->pluck('attribute_id')->unique();

        if ($submittedIds->diff($assigned->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                $field => ['One or more attributes are not assigned to the product category.'],
            ]);
        }

        foreach ($values as $value) {
            $attribute = $assigned->get($value['attribute_id']);
            if ($attribute === null || ! $this->valueValidator->isValid(
                $attribute->type,
                $value['value'],
                array_values(array_filter($attribute->options->pluck('value')->all(), 'is_string')),
            )) {
                throw ValidationException::withMessages([
                    $field => ['One or more values do not match the current attribute type or options.'],
                ]);
            }
        }

        if ($requireAll) {
            $missing = $assigned->filter(static function ($attribute): bool {
                $pivot = $attribute->getRelation('pivot');

                return $pivot instanceof Pivot && (bool) $pivot->getAttribute('is_required');
            })->pluck('id')->diff($submittedIds);
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
        $productsUseAttribute = $category->products()->whereHas(
            'attributeValues',
            fn ($query) => $query->whereIn('attribute_id', $attributeIds),
        )->exists();

        return $productsUseAttribute || (Schema::hasTable('product_variant_attribute_values') && $category->products()->whereHas(
            'variants.attributeValues',
            fn ($query) => $query->whereIn('attribute_id', $attributeIds),
        )->exists());
    }

    /**
     * @param  iterable<mixed>  $values
     * @return list<int>
     */
    private function integerIds(iterable $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (is_int($value)) {
                $ids[] = $value;
            }
        }

        return $ids;
    }
}
