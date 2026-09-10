<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function view(User $user, Category $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->allows($user, 'catalog.manage');
    }
}
