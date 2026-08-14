<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Category
    {
        $this->ensureParentCanBeAssigned(null, $attributes['parent_id'] ?? null);

        return DB::transaction(function () use ($actor, $attributes): Category {
            $category = Category::query()->create($attributes);
            $this->auditLogService->record($actor, 'category.created', $category);

            return $category;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Category $category, array $attributes): Category
    {
        if (array_key_exists('parent_id', $attributes)) {
            $this->ensureParentCanBeAssigned($category, $attributes['parent_id']);
        }
        if (($attributes['is_parent'] ?? true) === false && $category->children()->exists()) {
            throw ValidationException::withMessages(['is_parent' => ['A category with children must remain a parent category.']]);
        }

        return DB::transaction(function () use ($actor, $category, $attributes): Category {
            $category->fill($attributes)->save();
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

    private function ensureParentCanBeAssigned(?Category $category, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        if ($category?->id === $parentId) {
            throw ValidationException::withMessages(['parent_id' => ['A category cannot be its own parent.']]);
        }

        $parent = Category::query()->find($parentId);
        if ($parent?->is_parent !== true) {
            throw ValidationException::withMessages(['parent_id' => ['The selected category cannot be a parent category.']]);
        }
        while ($parent !== null) {
            if ($parent->id === $category?->id) {
                throw ValidationException::withMessages(['parent_id' => ['A category cannot be placed inside its descendant.']]);
            }
            $parent = $parent->parent;
        }
    }
}
