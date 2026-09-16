<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrator_can_filter_the_audit_log(): void
    {
        $actor = $this->userWithRole('administrator');
        $anotherActor = User::factory()->create(['name' => 'Другой сотрудник']);
        $role = $actor->roles()->sole();
        AuditLog::query()->create(['actor_id' => $actor->id, 'action' => 'auth.login', 'entity_type' => User::class, 'entity_id' => $actor->id, 'metadata' => ['status' => 'active', 'role_ids' => [$role->id]], 'occurred_at' => now()]);
        AuditLog::query()->create(['actor_id' => $anotherActor->id, 'action' => 'role.updated', 'occurred_at' => now()]);

        $response = $this->actingAs($actor)->getJson('/api/v1/admin/audit-logs?action=auth.login');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'auth.login')
            ->assertJsonPath('data.0.actor.name', $actor->name)
            ->assertJsonPath('data.0.entity.name', $actor->name)
            ->assertJsonPath('data.0.entity.email', $actor->email)
            ->assertJsonPath('data.0.details.0.label', 'Статус')
            ->assertJsonPath('data.0.details.0.value', 'Активен')
            ->assertJsonPath('data.0.details.1.label', 'Роли')
            ->assertJsonPath('data.0.details.1.value', $role->name);
    }

    public function test_audit_log_supports_every_documented_filter_and_pagination(): void
    {
        $actor = $this->userWithRole('administrator');
        $actor->update(['name' => 'Искомый сотрудник']);
        $anotherActor = User::factory()->create(['name' => 'Другой сотрудник']);
        $older = AuditLog::query()->create([
            'actor_id' => $actor->id,
            'action' => 'auth.login',
            'occurred_at' => '2026-09-10 12:00:00',
        ]);
        $matching = AuditLog::query()->create([
            'actor_id' => $actor->id,
            'action' => 'catalog.product-updated',
            'occurred_at' => '2026-09-15 12:00:00',
        ]);
        $newer = AuditLog::query()->create([
            'actor_id' => $anotherActor->id,
            'action' => 'order.updated',
            'occurred_at' => '2026-09-16 12:00:00',
        ]);

        $this->actingAs($actor)->getJson('/api/v1/admin/audit-logs?search='.rawurlencode('Искомый'))
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonMissing(['id' => $newer->id]);
        $this->actingAs($actor)->getJson('/api/v1/admin/audit-logs?action=catalog.product-updated')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $matching->id);
        $this->actingAs($actor)->getJson("/api/v1/admin/audit-logs?actor_id={$anotherActor->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $newer->id);
        $fromResponse = $this->actingAs($actor)->getJson('/api/v1/admin/audit-logs?date_from=2026-09-15')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->assertNotContains($older->id, collect($fromResponse->json('data'))->pluck('id'));
        $toResponse = $this->actingAs($actor)->getJson('/api/v1/admin/audit-logs?date_to=2026-09-15')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->assertNotContains($newer->id, collect($toResponse->json('data'))->pluck('id'));
        $this->actingAs($actor)->getJson('/api/v1/admin/audit-logs?per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1);
    }

    public function test_user_without_audit_log_permission_cannot_view_the_log(): void
    {
        $actor = $this->userWithRole('analyst');

        $this->actingAs($actor)->getJson('/api/v1/admin/audit-logs')->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
