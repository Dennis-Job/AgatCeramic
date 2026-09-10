<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy extends AuthorizesPermissions
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'orders.view');
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->allows($user, 'orders.manage');
    }

    public function managePayment(User $user, Order $order): bool
    {
        return $this->allows($user, 'payments.manage');
    }

    public function createComment(User $user, Order $order): bool
    {
        return $this->allows($user, 'orders.manage');
    }
}
