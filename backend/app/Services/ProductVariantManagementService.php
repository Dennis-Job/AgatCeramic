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
            $variant = $product->variants()->create($attributes);
            $this->auditLogService->record($actor, 'product.variant-created', $variant);

            return $variant;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, ProductVariant $variant, array $attributes): ProductVariant
    {
        return DB::transaction(function () use ($actor, $variant, $attributes): ProductVariant {
            $variant->fill($attributes)->save();
            $this->auditLogService->record($actor, 'product.variant-updated', $variant);

            return $variant;
        });
    }

    public function delete(User $actor, ProductVariant $variant): void
    {
        DB::transaction(function () use ($actor, $variant): void {
            $this->auditLogService->record($actor, 'product.variant-deleted', $variant);
            $variant->delete();
        });
    }
}
