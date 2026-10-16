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
        private readonly AuditLogService $audit,
    ) {}

    /**
     * The caller owns the transaction and durable checkpoint.
     *
     * @param  ProductPayload  $payload
     * @param  AttributePayload  $attributePayload
     * @param  array<string, mixed>  $auditContext
     */
    public function apply(User $actor, ?int $productId, array $payload, array $attributePayload, array $auditContext = []): string
    {
        return $this->applyWithResult($actor, $productId, $payload, $attributePayload, $auditContext)['operation'];
    }

    /**
     * @param  ProductPayload  $payload
     * @param  AttributePayload  $attributePayload
     * @param  array<string, mixed>  $auditContext
     * @return array{operation: 'created'|'updated', product: Product}
     */
    public function applyWithResult(User $actor, ?int $productId, array $payload, array $attributePayload, array $auditContext = []): array
    {
        $product = $productId === null ? null : Product::query()->lockForUpdate()->findOrFail($productId);
        $before = $product === null ? [] : $this->audit->capture($product);
        $activate = $payload['is_active'];
        $payload['is_active'] = false;

        if ($product === null) {
            $product = $this->products->create($actor, $payload, $auditContext, deferAudit: true);
            $operation = 'created';
        } else {
            if ($product->category_id !== $payload['category_id']) {
                $this->products->update($actor, $product, ['is_active' => false], $auditContext, deferAudit: true);
                $this->attributeValues->replace($actor, $product, [], $auditContext, deferProductAudit: true);
            }
            $product = $this->products->update($actor, $product, $payload, $auditContext, deferAudit: true);
            $operation = 'updated';
        }

        $this->attributeValues->replace($actor, $product, $attributePayload, $auditContext, deferProductAudit: true);
        if ($activate) {
            $product = $this->products->update($actor, $product, ['is_active' => true], $auditContext, deferAudit: true);
        }

        // The import row is one business change; temporary deactivation is an implementation detail.
        if ($operation === 'created') {
            $this->audit->record($actor, 'product.created', $product, $auditContext);
        } else {
            $this->audit->recordUpdated($actor, 'product.updated', $product, $before, $auditContext);
        }

        return ['operation' => $operation, 'product' => $product];
    }
}
