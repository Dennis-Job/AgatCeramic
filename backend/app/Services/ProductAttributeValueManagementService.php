<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductAttributeValueManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<int, array{attribute_id: int, value: mixed}> $attributes */
    public function replace(User $actor, Product $product, array $attributes): Product
    {
        return DB::transaction(function () use ($actor, $product, $attributes): Product {
            $attributeIds = collect($attributes)->pluck('attribute_id');
            $product->attributeValues()->whereNotIn('attribute_id', $attributeIds)->delete();

            foreach ($attributes as $attribute) {
                $product->attributeValues()->updateOrCreate(
                    ['attribute_id' => $attribute['attribute_id']],
                    ['value' => $attribute['value']],
                );
            }

            $this->auditLogService->record($actor, 'product.attributes-updated', $product, ['attribute_ids' => $attributeIds->all()]);

            return $product->load(['attributeValues.attribute.options']);
        });
    }
}
