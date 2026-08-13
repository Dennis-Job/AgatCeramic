<?php

namespace App\Services;

use App\Enums\AdminUserStatus;
use App\Http\Requests\Api\V1\Admin\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Admin\LoginRequest;
use App\Http\Requests\Api\V1\Admin\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAuthenticationService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

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
        $this->auditLogService->record($user, 'auth.login', $user);
    }

    public function logout(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        $this->auditLogService->record($user, 'auth.logout', $user);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function sendPasswordResetLink(ForgotPasswordRequest $request): void
    {
        $email = $request->string('email')->lower()->toString();
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->status !== AdminUserStatus::Active) {
            return;
        }

        Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(ResetPasswordRequest $request): void
    {
        $email = $request->string('email')->lower()->toString();
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->status !== AdminUserStatus::Active) {
            $this->throwInvalidPasswordReset();
        }

        $status = Password::reset([
            'email' => $email,
            'password' => $request->string('password')->toString(),
            'password_confirmation' => $request->string('password_confirmation')->toString(),
            'token' => $request->string('token')->toString(),
        ], function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table('sessions')->where('user_id', $user->getKey())->delete();
                $this->auditLogService->record($user, 'auth.password-reset.completed', $user);
            });
        });

        if ($status !== Password::PASSWORD_RESET) {
            $this->throwInvalidPasswordReset();
        }
    }

    /** @param array<string, mixed> $attributes */
    public function updateProfile(User $user, array $attributes): User
    {
        return DB::transaction(function () use ($user, $attributes): User {
            $passwordChanged = array_key_exists('password', $attributes);
            $changedFields = array_keys(array_diff_key($attributes, ['password' => true]));

            $user->fill($attributes);

            if ($passwordChanged) {
                $user->forceFill(['remember_token' => Str::random(60)]);
            }

            $user->save();

            if ($passwordChanged) {
                DB::table('sessions')->where('user_id', $user->getKey())->delete();
            }

            $this->auditLogService->record($user, 'auth.profile.updated', $user, [
                'changed_fields' => $changedFields,
                'credentials_changed' => $passwordChanged,
            ]);

            return $user->load('roles.permissions');
        });
    }

    private function throwInvalidPasswordReset(): never
    {
        throw ValidationException::withMessages([
            'token' => ['The password reset token is invalid or has expired.'],
        ]);
    }
}
