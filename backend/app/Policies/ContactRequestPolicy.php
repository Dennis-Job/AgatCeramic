<?php

namespace App\Policies;

use App\Models\ContactRequest;
use App\Models\User;

class ContactRequestPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'contacts.view');
    }

    public function view(User $user, ContactRequest $contactRequest): bool
    {
        return $this->viewAny($user);
    }

    public function assign(User $user, ContactRequest $contactRequest): bool
    {
        return $this->allows($user, 'contacts.manage');
    }

    public function viewAssignees(User $user): bool
    {
        return $this->allows($user, 'contacts.manage');
    }

    public function update(User $user, ContactRequest $contactRequest): bool
    {
        return $this->allows($user, 'contacts.manage');
    }

    public function createComment(User $user, ContactRequest $contactRequest): bool
    {
        return $this->update($user, $contactRequest);
    }
}
