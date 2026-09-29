<?php

namespace App\Policies;

use App\Models\User;

class HomePagePolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'content.manage');
    }

    public function update(User $user): bool
    {
        return $this->allows($user, 'content.manage');
    }
}
