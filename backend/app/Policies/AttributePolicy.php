<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;

class AttributePolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $this->allows($user, 'catalog.manage');
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $this->allows($user, 'catalog.manage');
    }
}
