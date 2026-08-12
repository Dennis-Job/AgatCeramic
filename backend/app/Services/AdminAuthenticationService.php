<?php

namespace App\Services;

use App\Enums\AdminUserStatus;
use App\Http\Requests\Api\V1\Admin\LoginRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminAuthenticationService
{
    public function login(LoginRequest $request): void
    {
        $user = User::query()
            ->where('email', $request->string('email')->lower()->toString())
            ->first();

        if ($user === null
            || $user->status !== AdminUserStatus::Active
            || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw new AuthenticationException;
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
    }

    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
