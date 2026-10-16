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
        private readonly ProductCompletenessService $completenessService,
        private readonly CatalogSkuService $skuService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $auditContext
     */
    public function create(User $actor, array $attributes, array $auditContext = [], bool $deferAudit = false): Product
    {
        return DB::transaction(function () use ($actor, $attributes, $auditContext, $deferAudit): Product {
            $category = Category::query()->whereKey($attributes['category_id'])->lockForUpdate()->firstOrFail();
            $attributes['sku'] = $this->skuService->generate($category);
            $product = Product::query()->create($attributes);
            if ($product->is_active) {
                $this->completenessService->assertCanActivate($product->load('category'));
            }
            if (! $deferAudit) {
                $this->auditLogService->record($actor, 'product.created', $product, $auditContext);
            }

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $auditContext
     */
    public function update(User $actor, Product $product, array $attributes, array $auditContext = [], bool $deferAudit = false): Product
    {
        return DB::transaction(function () use ($actor, $product, $attributes, $auditContext, $deferAudit): Product {
            $membership = $product->groupMembership()->first();
            if ($membership !== null) {
                foreach (['category_id', 'brand_id'] as $field) {
                    if (array_key_exists($field, $attributes) && $attributes[$field] !== $product->{$field}) {
                        throw ValidationException::withMessages([$field => ['A grouped product must be ungrouped before changing its category or brand.']]);
                    }
                }
            }
            if (array_key_exists('category_id', $attributes)) {
                $lockedCategoryIds = collect([$product->category_id, $attributes['category_id']])->unique()->sort()->values();
                $categories = Category::query()->whereIn('id', $lockedCategoryIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

                if (! $lockedCategoryIds->contains($product->category_id)) {
                    throw ValidationException::withMessages([
                        'category_id' => ['The product category changed concurrently. Retry the request.'],
                    ]);
                }

                $targetCategory = $categories->get($this->categoryId($attributes['category_id']));
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

            $before = $deferAudit ? [] : $this->auditLogService->capture($product);
            $product->fill($attributes)->save();
            if (($attributes['is_active'] ?? false) === true) {
                $this->completenessService->assertCanActivate($product->load('category'));
            }
            if (! $deferAudit) {
                $this->auditLogService->recordUpdated($actor, 'product.updated', $product, $before, $auditContext);
            }

            return $product;
        });
    }

    public function delete(User $actor, Product $product): void
    {
        DB::transaction(function () use ($actor, $product): void {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $membership = $product->groupMembership()->with('group')->first();
            $group = $membership?->group()->lockForUpdate()->first();
            $groupBefore = $group === null ? null : $this->auditLogService->capture($group);
            if ($group !== null && $group->products()->count() <= 2) {
                $this->auditLogService->record($actor, 'product-group.deleted', $group, ['trigger_product_id' => $product->id]);
                $group->delete();
            }
            $images = $product->images()->get(['disk', 'path'])->all();
            $this->auditLogService->record($actor, 'product.deleted', $product);
            foreach ($images as $image) {
                $this->storageCleanupService->schedule($image->disk, $image->path);
            }
            $product->delete();
            if ($group !== null && $group->exists && $groupBefore !== null) {
                $this->auditLogService->recordUpdated($actor, 'product-group.updated', $group, $groupBefore, ['trigger_product_id' => $product->id]);
            }

        });
    }

    private function categoryId(mixed $value): int
    {
        if (! is_int($value)) {
            throw new \LogicException('Validated category identifier is invalid.');
        }

        return $value;
    }
}
