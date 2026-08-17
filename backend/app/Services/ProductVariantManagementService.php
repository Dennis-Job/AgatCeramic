<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductVariantManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, Product $product, array $attributes): ProductVariant
    {
        return DB::transaction(function () use ($actor, $product, $attributes): ProductVariant {
            $attributeValues = $attributes['attribute_values'] ?? [];
            unset($attributes['attribute_values']);
            $variant = $product->variants()->create($attributes);
            $this->replaceAttributeValues($variant, $attributeValues);
            $this->auditLogService->record($actor, 'product.variant-created', $variant, ['attribute_ids' => collect($attributeValues)->pluck('attribute_id')->all()]);

            return $variant->load('attributeValues.attribute.options');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, ProductVariant $variant, array $attributes): ProductVariant
    {
        return DB::transaction(function () use ($actor, $variant, $attributes): ProductVariant {
            $hasAttributeValues = array_key_exists('attribute_values', $attributes);
            $attributeValues = $attributes['attribute_values'] ?? [];
            unset($attributes['attribute_values']);
            $variant->fill($attributes)->save();
            if ($hasAttributeValues) {
                $this->replaceAttributeValues($variant, $attributeValues);
            }
            $this->auditLogService->record($actor, 'product.variant-updated', $variant, $hasAttributeValues ? ['attribute_ids' => collect($attributeValues)->pluck('attribute_id')->all()] : []);

            return $variant->load('attributeValues.attribute.options');
        });
    }

    public function delete(User $actor, ProductVariant $variant): void
    {
        DB::transaction(function () use ($actor, $variant): void {
            $this->auditLogService->record($actor, 'product.variant-deleted', $variant);
            $variant->delete();
        });
    }

    /** @param array<int, array{attribute_id: int, value: mixed}> $attributeValues */
    private function replaceAttributeValues(ProductVariant $variant, array $attributeValues): void
    {
        $attributeIds = collect($attributeValues)->pluck('attribute_id');
        $variant->attributeValues()->whereNotIn('attribute_id', $attributeIds)->delete();

        foreach ($attributeValues as $attributeValue) {
            $variant->attributeValues()->updateOrCreate(
                ['attribute_id' => $attributeValue['attribute_id']],
                ['value' => $attributeValue['value']],
            );
        }
    }
}
