<?php

namespace App\Policies;

use App\Models\User;
use App\Services\PermissionChecker;

abstract class AuthorizesPermissions
{
    public function __construct(private readonly PermissionChecker $permissionChecker) {}

    protected function allows(User $user, string $permission): bool
    {
        return $this->permissionChecker->allows($user, $permission);
    }
}
