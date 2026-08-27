<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductAttributeValueManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CatalogAttributeIntegrityService $integrityService,
        private readonly ProductGroupManagementService $productGroupService,
    ) {}

    /** @param array<int, array{attribute_id: int, value: mixed}> $attributes */
    public function replace(User $actor, Product $product, array $attributes): Product
    {
        return DB::transaction(function () use ($actor, $product, $attributes): Product {
            $category = Category::query()->whereKey($product->category_id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($product->category_id !== $category->id) {
                throw ValidationException::withMessages(['attributes' => ['The product category changed concurrently. Retry the request.']]);
            }
            $product->setRelation('category', $category);

            $attributeIds = collect($attributes)->pluck('attribute_id');
            $this->integrityService->assertValuesMatchCategory($product, $attributes, 'attributes', $product->is_active);
            $product->attributeValues()->whereNotIn('attribute_id', $attributeIds)->delete();

            foreach ($attributes as $attribute) {
                $product->attributeValues()->updateOrCreate(
                    ['attribute_id' => $attribute['attribute_id']],
                    ['value' => $attribute['value']],
                );
            }

            $membership = $product->groupMembership()->with('group')->first();
            if ($membership !== null) {
                $this->productGroupService->revalidate($membership->group);
            }

            $this->auditLogService->record($actor, 'product.attributes-updated', $product, ['attribute_ids' => $attributeIds->all()]);

            return $product->load(['attributeValues.attribute.options']);
        });
    }
}
