<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Product
    {
        return DB::transaction(function () use ($actor, $attributes): Product {
            $product = Product::query()->create($attributes);
            $this->auditLogService->record($actor, 'product.created', $product);

            return $product;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Product $product, array $attributes): Product
    {
        return DB::transaction(function () use ($actor, $product, $attributes): Product {
            $product->fill($attributes)->save();
            $this->auditLogService->record($actor, 'product.updated', $product);

            return $product;
        });
    }

    public function delete(User $actor, Product $product): void
    {
        $images = DB::transaction(function () use ($actor, $product): array {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $images = $product->images()->get(['disk', 'path'])->all();
            $this->auditLogService->record($actor, 'product.deleted', $product);
            $product->delete();

            return $images;
        });

        foreach ($images as $image) {
            Storage::disk($image->disk)->delete($image->path);
        }
    }
}
