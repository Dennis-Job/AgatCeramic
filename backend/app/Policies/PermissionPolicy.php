<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('permissions.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->hasPermission('permissions.manage');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasPermission('permissions.manage');
    }
}
