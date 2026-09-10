<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

class PermissionPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'permissions.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $this->allows($user, 'permissions.manage');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $this->allows($user, 'permissions.manage');
    }
}
