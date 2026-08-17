<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductRelationManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<int, array{related_product_id: int, type: string, sort_order?: int}> $relations */
    public function replace(User $actor, Product $product, array $relations): Product
    {
        return DB::transaction(function () use ($actor, $product, $relations): Product {
            $relatedProductIds = collect($relations)->pluck('related_product_id')->map(static fn (mixed $id): int => (int) $id);
            $productIds = $relatedProductIds->concat([$product->id])->unique()->sort()->values();
            $lockedProducts = Product::query()->whereKey($productIds)->orderBy('id')->lockForUpdate()->get();

            if ($lockedProducts->count() !== $productIds->count()) {
                throw ValidationException::withMessages(['relations' => 'One or more related products are no longer available.']);
            }

            $reverseProductIds = ProductRelation::query()
                ->where('related_product_id', $product->id)
                ->whereIn('product_id', $relatedProductIds)
                ->pluck('product_id');
            foreach ($relatedProductIds as $index => $relatedProductId) {
                if ($reverseProductIds->contains($relatedProductId)) {
                    throw ValidationException::withMessages([
                        "relations.{$index}.related_product_id" => 'A reverse product relation already exists.',
                    ]);
                }
            }

            /** @var Product $product */
            $product = $lockedProducts->firstWhere('id', $product->id);
            $product->outgoingRelations()->delete();
            foreach ($relations as $index => $relation) {
                $product->outgoingRelations()->create([...$relation, 'sort_order' => $relation['sort_order'] ?? $index]);
            }
            $this->auditLogService->record($actor, 'product.relations-updated', $product, ['related_product_ids' => collect($relations)->pluck('related_product_id')->all()]);

            return $product->load('outgoingRelations.relatedProduct');
        });
    }
}
