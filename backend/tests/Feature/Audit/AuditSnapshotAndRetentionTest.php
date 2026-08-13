<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditSnapshotAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_api_uses_employee_snapshots_after_the_related_account_is_deleted(): void
    {
        $viewer = $this->auditViewer();
        $target = User::factory()->create(['name' => 'Удалённый сотрудник', 'email' => 'removed@example.test']);
        app(AuditLogService::class)->record($viewer, 'admin-user.deleted', $target);
        $target->delete();

        $this->actingAs($viewer)
            ->getJson('/api/v1/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.actor.name', $viewer->name)
            ->assertJsonPath('data.0.entity.name', 'Удалённый сотрудник')
            ->assertJsonPath('data.0.entity.email', 'removed@example.test');
    }

    public function test_prune_command_removes_only_audit_records_older_than_five_years(): void
    {
        config()->set('audit.retention_years', 5);
        $expired = AuditLog::query()->create(['action' => 'auth.login', 'occurred_at' => now()->subYears(5)->subSecond()]);
        $retained = AuditLog::query()->create(['action' => 'auth.logout', 'occurred_at' => now()->subYears(5)->addSecond()]);

        $this->artisan('audit:prune')->assertSuccessful();

        $this->assertModelMissing($expired);
        $this->assertModelExists($retained);
    }

    private function auditViewer(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::query()->where('slug', 'administrator')->sole());

        return $viewer;
    }
}
