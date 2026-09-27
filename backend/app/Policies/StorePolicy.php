<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'content.manage');
    }

    public function view(User $user, Store $store): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Store $store): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Store $store): bool
    {
        return $this->viewAny($user);
    }
}
