<?php

namespace App\Policies;

use App\Models\ProductCollection;
use App\Models\User;

class ProductCollectionPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function view(User $user, ProductCollection $collection): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ProductCollection $collection): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ProductCollection $collection): bool
    {
        return $this->viewAny($user);
    }
}
