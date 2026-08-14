<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CategoryManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Category
    {
        return DB::transaction(function () use ($actor, $attributes): Category {
            $category = Category::query()->create($attributes);
            $this->auditLogService->record($actor, 'category.created', $category);

            return $category;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Category $category, array $attributes): Category
    {
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
}
