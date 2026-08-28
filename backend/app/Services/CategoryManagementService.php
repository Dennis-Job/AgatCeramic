<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CatalogSkuService $skuService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Category
    {
        return DB::transaction(function () use ($actor, $attributes): Category {
            $parentId = $attributes['parent_id'] ?? null;
            $parent = $this->lockParent($parentId);
            $this->ensureParentCanBeAssigned(null, $parentId, $parent);
            $category = new Category($attributes);
            $category->sku_prefix = $this->skuService->prefixForNewCategory($parent);
            $category->save();
            $this->auditLogService->record($actor, 'category.created', $category);

            return $category;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($actor, $category, $attributes): Category {
            $categories = array_key_exists('parent_id', $attributes) && $attributes['parent_id'] !== null
                ? Category::query()->orderBy('id')->lockForUpdate()->get()
                : null;
            $category = $categories?->firstWhere('id', $category->id)
                ?? Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $originalParentId = $category->parent_id;
            $parent = null;

            if (array_key_exists('parent_id', $attributes)) {
                $parent = $attributes['parent_id'] === null
                    ? null
                    : $categories?->firstWhere('id', $attributes['parent_id']);
                $this->ensureParentCanBeAssigned($category, $attributes['parent_id'], $parent, $categories);
            }
            if (($attributes['is_parent'] ?? true) === false && $category->children()->exists()) {
                throw ValidationException::withMessages(['is_parent' => ['A category with children must remain a parent category.']]);
            }

            $category->fill($attributes)->save();
            if (array_key_exists('parent_id', $attributes) && $attributes['parent_id'] !== $originalParentId) {
                $this->skuService->reassignSubtree($category, $parent);
            }
            $this->auditLogService->record($actor, 'category.updated', $category);

            return $category;
        });
    }

    public function delete(User $actor, Category $category): void
    {
        DB::transaction(function () use ($actor, $category): void {
            $this->auditLogService->record($actor, 'category.deleted', $category);
            $category->delete();
        });
    }

    /** @return Collection<int, Category> */
    public function tree(): Collection
    {
        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();
        $byParent = $categories->groupBy('parent_id');
        $attach = function (Category $category) use (&$attach, $byParent): Category {
            $category->setRelation('children', $byParent->get($category->id, collect())->map($attach)->values());

            return $category;
        };

        return $byParent->get(null, collect())->map($attach)->values();
    }

    private function lockParent(?int $parentId): ?Category
    {
        if ($parentId === null) {
            return null;
        }

        return Category::query()->whereKey($parentId)->lockForUpdate()->first();
    }

    /** @param Collection<int, Category>|null $categories */
    private function ensureParentCanBeAssigned(?Category $category, ?int $parentId, ?Category $parent, ?Collection $categories = null): void
    {
        if ($parentId === null) {
            return;
        }
        if ($category?->id === $parentId) {
            throw ValidationException::withMessages(['parent_id' => ['A category cannot be its own parent.']]);
        }

        if ($parent?->is_parent !== true) {
            throw ValidationException::withMessages(['parent_id' => ['The selected category cannot be a parent category.']]);
        }
        while ($parent !== null) {
            if ($parent->id === $category?->id) {
                throw ValidationException::withMessages(['parent_id' => ['A category cannot be placed inside its descendant.']]);
            }
            $parent = $categories?->firstWhere('id', $parent->parent_id) ?? $parent->parent;
        }
    }
}
