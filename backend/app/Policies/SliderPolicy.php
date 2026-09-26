<?php

namespace App\Policies;

use App\Models\Slider;
use App\Models\User;

class SliderPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'content.manage');
    }

    public function view(User $user, Slider $slider): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Slider $slider): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Slider $slider): bool
    {
        return $this->viewAny($user);
    }
}
