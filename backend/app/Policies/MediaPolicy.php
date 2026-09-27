<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'media.manage')
            || $this->allows($user, 'catalog.manage')
            || $this->allows($user, 'content.manage');
    }

    public function view(User $user, Media $media): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'media.manage');
    }

    public function update(User $user, Media $media): bool
    {
        return $this->allows($user, 'media.manage');
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->allows($user, 'media.manage');
    }
}
