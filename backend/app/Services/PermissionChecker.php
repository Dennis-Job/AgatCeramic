<?php

namespace App\Services;

use App\Models\User;

class PermissionChecker
{
    public function allows(User $user, string $code): bool
    {
        return $user->roles()
            ->whereHas('permissions', static fn ($query) => $query->where('code', $code))
            ->exists();
    }
}
