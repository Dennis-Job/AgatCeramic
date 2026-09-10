<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'admin-users.view');
    }

    public function view(User $user, User $subject): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'admin-users.manage');
    }

    public function update(User $user, User $subject): bool
    {
        return $this->allows($user, 'admin-users.manage');
    }

    public function delete(User $user, User $subject): bool
    {
        return $this->allows($user, 'admin-users.manage');
    }
}
