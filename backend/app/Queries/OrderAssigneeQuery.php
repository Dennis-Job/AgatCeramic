<?php

namespace App\Queries;

use App\Enums\AdminUserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class OrderAssigneeQuery
{
    /** @return Collection<int, User> */
    public function get(): Collection
    {
        return User::query()
            ->where('status', AdminUserStatus::Active->value)
            ->whereHas('roles.permissions', fn ($query) => $query->where('code', 'orders.manage'))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);
    }
}
