<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CategoryAttributeManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CatalogAttributeIntegrityService $integrityService,
    ) {}

    /** @param array<int, array{id: int, sort_order?: int}> $attributes */
    public function replace(User $actor, Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($actor, $category, $attributes): Category {
            $category = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $category->products()->orderBy('id')->lockForUpdate()->get();
            $assignments = collect($attributes)->mapWithKeys(static fn (array $attribute, int $index): array => [
                $attribute['id'] => ['sort_order' => $attribute['sort_order'] ?? $index],
            ])->all();

            $this->integrityService->assertCategoryAssignmentsCanBeReplaced($category, array_keys($assignments));

            $category->attributes()->sync($assignments);
            $this->auditLogService->record($actor, 'category.attributes-updated', $category, [
                'attribute_ids' => array_keys($assignments),
            ]);

            return $category->load(['attributes.options']);
        });
    }

    /** @param array<int, array{id: int, sort_order?: int}> $groups */
    public function replaceGroups(User $actor, Category $category, array $groups): Category
    {
        return DB::transaction(function () use ($actor, $category, $groups): Category {
            $category = Category::query()->whereKey($category->id)->lockForUpdate()->firstOrFail();
            $category->products()->orderBy('id')->lockForUpdate()->get();
            $assignments = collect($groups)->mapWithKeys(static fn (array $group, int $index): array => [$group['id'] => ['sort_order' => $group['sort_order'] ?? $index]])->all();
            $removedAttributeIds = Attribute::query()
                ->whereNotNull('attribute_group_id')
                ->when($assignments !== [], fn ($query) => $query->whereNotIn('attribute_group_id', array_keys($assignments)))
                ->when($assignments === [], fn ($query) => $query)
                ->pluck('id');
            $remainingAttributeIds = $category->attributes()->whereNotIn('attributes.id', $removedAttributeIds)->pluck('attributes.id')->all();

            $this->integrityService->assertCategoryAssignmentsCanBeReplaced($category, $remainingAttributeIds, 'attribute_groups');

            $category->attributeGroups()->sync($assignments);
            $category->attributes()->detach($removedAttributeIds);
            $this->auditLogService->record($actor, 'category.attribute-groups-updated', $category, ['attribute_group_ids' => array_keys($assignments)]);

            return $category->load('attributeGroups');
        });
    }
}
