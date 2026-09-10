<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'imports.manage');
    }

    public function import(User $user): bool
    {
        return $this->allows($user, 'imports.manage');
    }
}
