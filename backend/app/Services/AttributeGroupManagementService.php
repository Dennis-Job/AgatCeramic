<?php

namespace App\Services;

use App\Models\AttributeGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AttributeGroupManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): AttributeGroup
    {
        return DB::transaction(function () use ($actor, $attributes): AttributeGroup {
            $group = AttributeGroup::query()->create($attributes);
            $this->auditLogService->record($actor, 'attribute-group.created', $group);

            return $group;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, AttributeGroup $group, array $attributes): AttributeGroup
    {
        return DB::transaction(function () use ($actor, $group, $attributes): AttributeGroup {
            $group = AttributeGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $before = $this->auditLogService->capture($group);
            $group->fill($attributes)->save();
            $this->auditLogService->recordUpdated($actor, 'attribute-group.updated', $group, $before);

            return $group;
        });
    }

    public function delete(User $actor, AttributeGroup $group): void
    {
        DB::transaction(function () use ($actor, $group): void {
            $group = AttributeGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $this->auditLogService->record($actor, 'attribute-group.deleted', $group);
            $group->delete();
        });
    }
}
