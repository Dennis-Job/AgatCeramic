<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CatalogAttributeIntegrityService $integrityService,
        private readonly StorageCleanupService $storageCleanupService,
    ) {}

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
            if (array_key_exists('category_id', $attributes)) {
                $lockedCategoryIds = collect([$product->category_id, $attributes['category_id']])->unique()->sort()->values();
                $categories = Category::query()->whereIn('id', $lockedCategoryIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

                if (! $lockedCategoryIds->contains($product->category_id)) {
                    throw ValidationException::withMessages([
                        'category_id' => ['The product category changed concurrently. Retry the request.'],
                    ]);
                }

                $targetCategory = $categories->get((int) $attributes['category_id']);
                if ($targetCategory === null) {
                    throw ValidationException::withMessages([
                        'category_id' => ['The selected category is no longer available.'],
                    ]);
                }

                if ($targetCategory->id !== $product->category_id) {
                    $this->integrityService->assertProductCanUseCategory($product, $targetCategory);
                }
            } else {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            }

            $product->fill($attributes)->save();
            $this->auditLogService->record($actor, 'product.updated', $product);

            return $product;
        });
    }

    public function delete(User $actor, Product $product): void
    {
        DB::transaction(function () use ($actor, $product): void {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $images = $product->images()->get(['disk', 'path'])->all();
            $this->auditLogService->record($actor, 'product.deleted', $product);
            foreach ($images as $image) {
                $this->storageCleanupService->schedule($image->disk, $image->path);
            }
            $product->delete();

        });
    }
}
