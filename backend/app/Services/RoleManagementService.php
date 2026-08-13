<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): Role
    {
        $this->ensureActorCanAssignPermissions($actor, $attributes['permission_ids']);

        return DB::transaction(function () use ($actor, $attributes): Role {
            $permissionIds = Arr::pull($attributes, 'permission_ids');
            $role = Role::query()->create($attributes);
            $role->permissions()->sync($permissionIds);
            $this->auditLogService->record($actor, 'role.created', $role, ['permission_ids' => $permissionIds]);

            return $role->load('permissions');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, Role $role, array $attributes): Role
    {
        $this->ensureSystemRoleIdentityIsUnchanged($role, $attributes);
        if (array_key_exists('permission_ids', $attributes)) {
            $this->ensureActorCanAssignPermissions($actor, $attributes['permission_ids']);
        }

        return DB::transaction(function () use ($actor, $role, $attributes): Role {
            $permissionIds = Arr::pull($attributes, 'permission_ids');
            $role->fill($attributes)->save();
            if ($permissionIds !== null) {
                $role->permissions()->sync($permissionIds);
            }

            $this->auditLogService->record($actor, 'role.updated', $role, [
                'permission_ids' => $role->permissions()->pluck('permissions.id')->all(),
            ]);

            return $role->load('permissions');
        });
    }

    public function delete(User $actor, Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => ['System roles cannot be deleted.']]);
        }

        DB::transaction(function () use ($actor, $role): void {
            $this->auditLogService->record($actor, 'role.deleted', $role, ['slug' => $role->slug]);
            $role->delete();
        });
    }

    /** @param list<int> $permissionIds */
    private function ensureActorCanAssignPermissions(User $actor, array $permissionIds): void
    {
        $actorPermissionIds = $actor->roles()
            ->with('permissions:id')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('id'))
            ->unique()
            ->all();

        if (array_diff($permissionIds, $actorPermissionIds) !== []) {
            throw ValidationException::withMessages(['permission_ids' => ['You can assign only permissions you already hold.']]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function ensureSystemRoleIdentityIsUnchanged(Role $role, array $attributes): void
    {
        if ($role->is_system && (array_key_exists('name', $attributes) || array_key_exists('slug', $attributes))) {
            throw ValidationException::withMessages(['role' => ['System role name and slug cannot be changed.']]);
        }
    }
}
