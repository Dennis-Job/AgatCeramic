<?php

namespace Tests\Integration;

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresAuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_trigger_rejects_direct_audit_log_updates(): void
    {
        $this->requirePostgres();
        $auditLog = $this->auditLog();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('audit_logs are immutable');

        AuditLog::query()->whereKey($auditLog->getKey())->update(['action' => 'auth.logout']);
    }

    public function test_postgresql_trigger_rejects_direct_audit_log_deletes(): void
    {
        $this->requirePostgres();
        $auditLog = $this->auditLog();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('audit_logs are immutable');

        AuditLog::query()->whereKey($auditLog->getKey())->delete();
    }

    public function test_retention_command_can_delete_expired_audit_logs_in_postgresql(): void
    {
        $this->requirePostgres();
        config()->set('audit.retention_years', 5);
        $expired = $this->auditLog(now()->subYears(5)->subSecond());
        $retained = $this->auditLog(now()->subYears(5)->addSecond());

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $expired->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $retained->id]);
    }

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('This integration test requires PostgreSQL.');
        }
    }

    private function auditLog(mixed $occurredAt = null): AuditLog
    {
        return AuditLog::query()->create([
            'action' => 'auth.login',
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}
