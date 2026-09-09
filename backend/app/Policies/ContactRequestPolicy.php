<?php

namespace App\Policies;

use App\Models\ContactRequest;
use App\Models\User;

class ContactRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contacts.view');
    }

    public function view(User $user, ContactRequest $contactRequest): bool
    {
        return $this->viewAny($user);
    }

    public function assign(User $user, ContactRequest $contactRequest): bool
    {
        return $user->hasPermission('contacts.manage');
    }

    public function update(User $user, ContactRequest $contactRequest): bool
    {
        return $user->hasPermission('contacts.manage');
    }

    public function createComment(User $user, ContactRequest $contactRequest): bool
    {
        return $this->update($user, $contactRequest);
    }
}
