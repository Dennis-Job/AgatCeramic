<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allows($user, 'roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->allows($user, 'roles.manage');
    }
}
