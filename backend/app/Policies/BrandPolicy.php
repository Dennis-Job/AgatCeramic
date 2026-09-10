<?php

namespace App\Policies;

use App\Models\Brand;
use App\Models\User;

class BrandPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function view(User $user, Brand $brand): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function update(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function delete(User $user, Brand $brand): bool
    {
        return $this->allows($user, 'catalog.manage');
    }
}
