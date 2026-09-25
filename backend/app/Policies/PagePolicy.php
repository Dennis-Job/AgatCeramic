<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'content.manage');
    }

    public function view(User $user, Page $page): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Page $page): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->viewAny($user);
    }
}
