<?php

namespace App\Policies;

use App\Models\ProductGroup;
use App\Models\User;

class ProductGroupPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function view(User $user, ProductGroup $group): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ProductGroup $group): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ProductGroup $group): bool
    {
        return $this->viewAny($user);
    }
}
