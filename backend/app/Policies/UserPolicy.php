<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('admin-users.view');
    }

    public function view(User $user, User $subject): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('admin-users.manage');
    }

    public function update(User $user, User $subject): bool
    {
        return $user->hasPermission('admin-users.manage');
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->hasPermission('admin-users.manage');
    }
}
