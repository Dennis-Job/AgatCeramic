<?php

namespace App\Policies;

use App\Models\AttributeGroup;
use App\Models\User;

class AttributeGroupPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function view(User $user, AttributeGroup $group): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function update(User $user, AttributeGroup $group): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function delete(User $user, AttributeGroup $group): bool
    {
        return $this->allows($user, 'catalog.manage');
    }
}
