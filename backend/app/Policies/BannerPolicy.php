<?php

namespace App\Policies;

use App\Models\Banner;
use App\Models\User;

class BannerPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'content.manage');
    }

    public function view(User $user, Banner $banner): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Banner $banner): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Banner $banner): bool
    {
        return $this->viewAny($user);
    }
}
