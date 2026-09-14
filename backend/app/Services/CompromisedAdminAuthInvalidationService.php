<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompromisedAdminAuthInvalidationService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @return array{users: int, sessions: int, reset_tokens: int} */
    public function invalidate(): array
    {
        return DB::transaction(function (): array {
            $userCount = 0;

            User::query()
                ->select('id')
                ->orderBy('id')
                ->eachById(function (User $user) use (&$userCount): void {
                    $user->forceFill([
                        'password' => Hash::make(Str::random(64)),
                        'remember_token' => null,
                    ])->saveQuietly();
                    $userCount++;
                });

            $sessionCount = DB::table('sessions')->delete();
            $resetTokenCount = DB::table('password_reset_tokens')->delete();

            $this->auditLogService->record(null, 'security.admin-auth-invalidated', metadata: [
                'user_count' => $userCount,
                'session_count' => $sessionCount,
                'reset_token_count' => $resetTokenCount,
            ]);

            return [
                'users' => $userCount,
                'sessions' => $sessionCount,
                'reset_tokens' => $resetTokenCount,
            ];
        });
    }
}
