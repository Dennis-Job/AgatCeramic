<?php

namespace App\Queries;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ContactAssigneeQuery
{
    /** @return Collection<int, User> */
    public function get(): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('code', 'contacts.manage'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
