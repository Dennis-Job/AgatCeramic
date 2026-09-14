<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvalidateCompromisedAdminAuthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_explicit_force_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'known-password']);

        $this->artisan('security:invalidate-compromised-admin-auth')->assertFailed();

        $this->assertTrue(Hash::check('known-password', $user->fresh()->password));
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_it_invalidates_all_administrator_authentication_state_without_logging_pii(): void
    {
        $firstUser = User::factory()->create(['password' => 'first-known-password']);
        $secondUser = User::factory()->create(['password' => 'second-known-password']);

        DB::table('sessions')->insert([
            [
                'id' => 'first-session',
                'user_id' => $firstUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => 'test-payload',
                'last_activity' => now()->timestamp,
            ],
            [
                'id' => 'second-session',
                'user_id' => $secondUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => 'test-payload',
                'last_activity' => now()->timestamp,
            ],
        ]);
        DB::table('password_reset_tokens')->insert([
            [
                'email' => $firstUser->email,
                'token' => 'first-reset-token',
                'created_at' => now(),
            ],
            [
                'email' => $secondUser->email,
                'token' => 'second-reset-token',
                'created_at' => now(),
            ],
        ]);

        $this->artisan('security:invalidate-compromised-admin-auth', ['--force' => true])
            ->expectsOutputToContain('Invalidated 2 administrator credential(s), 2 session(s), and 2 reset token(s).')
            ->assertSuccessful();

        $this->assertFalse(Hash::check('first-known-password', $firstUser->fresh()->password));
        $this->assertFalse(Hash::check('second-known-password', $secondUser->fresh()->password));
        $this->assertNull($firstUser->fresh()->remember_token);
        $this->assertNull($secondUser->fresh()->remember_token);
        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => null,
            'action' => 'security.admin-auth-invalidated',
            'entity_type' => null,
            'entity_id' => null,
        ]);

        $auditLog = DB::table('audit_logs')->where('action', 'security.admin-auth-invalidated')->first();
        $this->assertNotNull($auditLog);
        $serializedAuditLog = json_encode($auditLog, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($firstUser->email, $serializedAuditLog);
        $this->assertStringNotContainsString($secondUser->email, $serializedAuditLog);
    }
}
