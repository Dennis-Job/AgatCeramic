<?php

namespace App\Policies;

use App\Models\AttributeGroup;
use App\Models\User;

class AttributeGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function view(User $user, AttributeGroup $group): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function update(User $user, AttributeGroup $group): bool
    {
        return $user->hasPermission('catalog.manage');
    }

    public function delete(User $user, AttributeGroup $group): bool
    {
        return $user->hasPermission('catalog.manage');
    }
}
