<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CategoryAttributeManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<int, array{id: int, sort_order?: int}> $attributes */
    public function replace(User $actor, Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($actor, $category, $attributes): Category {
            $assignments = collect($attributes)->mapWithKeys(static fn (array $attribute, int $index): array => [
                $attribute['id'] => ['sort_order' => $attribute['sort_order'] ?? $index],
            ])->all();

            $category->attributes()->sync($assignments);
            $this->auditLogService->record($actor, 'category.attributes-updated', $category, [
                'attribute_ids' => array_keys($assignments),
            ]);

            return $category->load(['attributes.options']);
        });
    }
}
