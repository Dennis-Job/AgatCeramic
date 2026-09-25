<?php

namespace App\Policies;

use App\Models\User;

class SiteSettingPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'settings.manage');
    }

    public function update(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function recordApproval(User $user): bool
    {
        return $this->allows($user, 'settings.approve');
    }
}
