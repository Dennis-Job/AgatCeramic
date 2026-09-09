<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Return the active administrator guaranteed by the protected route group.
     *
     * The runtime check keeps the controller boundary safe even if a protected
     * action is later moved outside the current middleware group.
     */
    protected function authenticatedAdmin(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
