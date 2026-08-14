<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductRelationManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<int, array{related_product_id: int, type: string, sort_order?: int}> $relations */
    public function replace(User $actor, Product $product, array $relations): Product
    {
        return DB::transaction(function () use ($actor, $product, $relations): Product {
            $product->outgoingRelations()->delete();
            foreach ($relations as $index => $relation) {
                $product->outgoingRelations()->create([...$relation, 'sort_order' => $relation['sort_order'] ?? $index]);
            }
            $this->auditLogService->record($actor, 'product.relations-updated', $product, ['related_product_ids' => collect($relations)->pluck('related_product_id')->all()]);

            return $product->load('outgoingRelations.relatedProduct');
        });
    }
}
