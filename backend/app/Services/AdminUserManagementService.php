<?php

namespace App\Services;

use App\Enums\AdminUserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserManagementService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, array $attributes): User
    {
        return DB::transaction(function () use ($actor, $attributes): User {
            $roleIds = Arr::pull($attributes, 'role_ids');
            $user = User::query()->create($attributes);
            $user->roles()->sync($roleIds);
            $this->auditLogService->record($actor, 'admin-user.created', $user, [
                'status' => $user->status->value,
                'role_ids' => $roleIds,
            ]);

            return $user->load('roles');
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(User $actor, User $user, array $attributes): User
    {
        $this->ensureNotBlockingSelf($actor, $user, $attributes);
        $this->ensureActiveSuperAdminRemains($user, $attributes);

        return DB::transaction(function () use ($actor, $user, $attributes): User {
            $roleIds = Arr::pull($attributes, 'role_ids');
            $user->fill($attributes)->save();

            if ($roleIds !== null) {
                $user->roles()->sync($roleIds);
            }

            $this->auditLogService->record($actor, 'admin-user.updated', $user, [
                'status' => $user->status->value,
                'role_ids' => $user->roles()->pluck('roles.id')->all(),
            ]);

            return $user->load('roles');
        });
    }

    public function delete(User $actor, User $user): void
    {
        if ($actor->is($user)) {
            throw ValidationException::withMessages(['user' => ['You cannot delete your own account.']]);
        }

        $this->ensureActiveSuperAdminRemains($user, ['status' => AdminUserStatus::Blocked->value, 'role_ids' => []]);

        DB::transaction(function () use ($actor, $user): void {
            $this->auditLogService->record($actor, 'admin-user.deleted', $user, [
                'status' => $user->status->value,
                'role_ids' => $user->roles()->pluck('roles.id')->all(),
            ]);
            $user->delete();
        });
    }

    /** @param array<string, mixed> $attributes */
    private function ensureNotBlockingSelf(User $actor, User $user, array $attributes): void
    {
        if ($actor->is($user) && ($attributes['status'] ?? null) === AdminUserStatus::Blocked->value) {
            throw ValidationException::withMessages(['status' => ['You cannot block your own account.']]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function ensureActiveSuperAdminRemains(User $user, array $attributes): void
    {
        $superAdminRole = Role::query()->where('slug', 'super-admin')->first();
        if ($superAdminRole === null || $user->status !== AdminUserStatus::Active || ! $user->roles()->whereKey($superAdminRole->id)->exists()) {
            return;
        }

        $willRemainActiveSuperAdmin = ($attributes['status'] ?? $user->status->value) === AdminUserStatus::Active->value
            && (! array_key_exists('role_ids', $attributes) || in_array($superAdminRole->id, $attributes['role_ids'], true));

        if ($willRemainActiveSuperAdmin) {
            return;
        }

        $activeSuperAdmins = User::query()
            ->where('status', AdminUserStatus::Active->value)
            ->whereHas('roles', static fn ($query) => $query->where('roles.id', $superAdminRole->id))
            ->count();

        if ($activeSuperAdmins <= 1) {
            throw ValidationException::withMessages(['role_ids' => ['At least one active Super Admin must remain.']]);
        }
    }
}
