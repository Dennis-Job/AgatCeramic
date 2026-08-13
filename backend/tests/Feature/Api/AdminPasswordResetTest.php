<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_receives_a_password_reset_link_without_exposing_the_account(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/forgot-password', ['email' => 'ADMIN@example.test'])
            ->assertNoContent();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return str_contains($mail->actionUrl, 'http://localhost:5173/reset-password?')
                && str_contains($mail->actionUrl, 'email=admin%40example.test');
        });
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unknown_or_blocked_admin_receives_the_same_empty_response_without_a_notification(): void
    {
        Notification::fake();
        $blocked = User::factory()->blocked()->create(['email' => 'blocked@example.test']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/forgot-password', ['email' => 'unknown@example.test'])
            ->assertNoContent();
        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/forgot-password', ['email' => $blocked->email])
            ->assertNoContent();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_active_admin_can_reset_a_password_and_existing_sessions_are_revoked(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $token = app('auth.password.broker')->createToken($user);
        $this->app['db']->table('sessions')->insert([
            'id' => 'existing-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/reset-password', [
                'email' => 'ADMIN@example.test',
                'token' => $token,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertNoContent();

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'existing-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.password-reset.completed',
            'actor_id' => $user->id,
            'entity_id' => $user->id,
        ]);
    }

    public function test_invalid_reset_token_does_not_change_the_password(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/reset-password', [
                'email' => $user->email,
                'token' => 'invalid-token',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
