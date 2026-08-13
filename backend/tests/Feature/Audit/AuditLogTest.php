<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_and_logout_are_audited(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);

        $this->withHeader('Origin', 'http://localhost:5173')
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->withoutMiddleware(ThrottleRequests::class)
            ->postJson('/api/v1/admin/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:5173')
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->postJson('/api/v1/admin/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.login',
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.logout',
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
        $this->assertNotNull(AuditLog::query()->first()->occurred_at);
    }

    public function test_metadata_is_sanitized_before_it_is_persisted(): void
    {
        $user = User::factory()->create();

        $auditLog = app(AuditLogService::class)->record($user, 'admin-users.update', $user, [
            'email' => 'staff@example.test',
            'phone' => '+7 (999) 123-45-67',
            'changes' => [
                'password' => 'plain-text-password',
                'status' => 'blocked',
            ],
            'affected_records' => 1,
        ]);

        $this->assertSame([
            'email' => '[redacted]',
            'phone' => '[redacted]',
            'changes' => [
                'password' => '[redacted]',
                'status' => 'blocked',
            ],
            'affected_records' => 1,
        ], $auditLog->fresh()->metadata);
    }
}
