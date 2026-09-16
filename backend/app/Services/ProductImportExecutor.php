<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;

/**
 * Applies a prevalidated product mutation while preserving deactivate/attributes/reactivate ordering.
 *
 * @phpstan-import-type ProductPayload from ProductImportService
 * @phpstan-import-type AttributePayload from ProductImportService
 */
final class ProductImportExecutor
{
    public function __construct(
        private readonly ProductManagementService $products,
        private readonly ProductAttributeValueManagementService $attributeValues,
    ) {}

    /**
     * The caller owns the transaction and durable checkpoint.
     *
     * @param  ProductPayload  $payload
     * @param  AttributePayload  $attributePayload
     */
    public function apply(User $actor, ?int $productId, array $payload, array $attributePayload): string
    {
        return $this->applyWithResult($actor, $productId, $payload, $attributePayload)['operation'];
    }

    /**
     * @param  ProductPayload  $payload
     * @param  AttributePayload  $attributePayload
     * @return array{operation: 'created'|'updated', product: Product}
     */
    public function applyWithResult(User $actor, ?int $productId, array $payload, array $attributePayload): array
    {
        $product = $productId === null ? null : Product::query()->lockForUpdate()->findOrFail($productId);
        $activate = $payload['is_active'];
        $payload['is_active'] = false;

        if ($product === null) {
            $product = $this->products->create($actor, $payload);
            $operation = 'created';
        } else {
            if ($product->category_id !== $payload['category_id']) {
                $this->products->update($actor, $product, ['is_active' => false]);
                $this->attributeValues->replace($actor, $product, []);
            }
            $product = $this->products->update($actor, $product, $payload);
            $operation = 'updated';
        }

        $this->attributeValues->replace($actor, $product, $attributePayload);
        if ($activate) {
            $this->products->update($actor, $product, ['is_active' => true]);
        }

        return ['operation' => $operation, 'product' => $product];
    }
}
