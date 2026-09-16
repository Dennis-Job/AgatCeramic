<?php

namespace App\Services;

use App\Enums\AdminUserStatus;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAuthenticationService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function login(string $email, string $password): User
    {
        $user = User::query()
            ->where('email', strtolower($email))
            ->first();

        if ($user === null
            || $user->getRawOriginal('status') !== AdminUserStatus::Active->value
            || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException;
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->auditLogService->record($user, 'auth.login', $user);

        return $user;
    }

    public function recordLogout(User $user): void
    {
        $this->auditLogService->record($user, 'auth.logout', $user);
    }

    public function sendPasswordResetLink(string $email): void
    {
        $email = strtolower($email);
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->getRawOriginal('status') !== AdminUserStatus::Active->value) {
            return;
        }

        Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(string $email, string $password, string $passwordConfirmation, string $token): void
    {
        $email = strtolower($email);
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->getRawOriginal('status') !== AdminUserStatus::Active->value) {
            $this->throwInvalidPasswordReset();
        }

        $status = Password::reset([
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
            'token' => $token,
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
