<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_lifecycle_visibility_and_weekly_hours(): void
    {
        $manager = $this->userWithRole('content-manager');
        $created = $this->actingAs($manager)->postJson('/api/v1/admin/stores', [
            'name' => 'Магазин на Ленина', 'address' => 'Москва, ул. Ленина, 1', 'phone' => '+7 495 123-45-67',
        ])->assertCreated()->assertJsonPath('data.is_published', false)
            ->assertJsonCount(7, 'data.working_hours');
        $id = $created->json('data.id');
        $this->getJson('/api/v1/stores')->assertJsonCount(0, 'data');

        $hours = [];
        foreach (range(1, 7) as $weekday) {
            $hours[] = $weekday <= 5
                ? ['weekday' => $weekday, 'is_closed' => false, 'opens_at' => '09:00', 'closes_at' => '18:00']
                : ['weekday' => $weekday, 'is_closed' => true, 'opens_at' => null, 'closes_at' => null];
        }
        $this->actingAs($manager)->putJson("/api/v1/admin/stores/{$id}/working-hours", ['working_hours' => $hours])
            ->assertOk()->assertJsonPath('data.working_hours.0.opens_at', '09:00')
            ->assertJsonPath('data.working_hours.6.is_closed', true);
        $this->actingAs($manager)->patchJson("/api/v1/admin/stores/{$id}", ['is_published' => true])
            ->assertOk();
        $this->getJson('/api/v1/stores')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.address', 'Москва, ул. Ленина, 1')
            ->assertJsonPath('data.0.working_hours.0.closes_at', '18:00');
        $this->actingAs($manager)->deleteJson("/api/v1/admin/stores/{$id}")->assertNoContent();
        $this->assertDatabaseCount('store_working_hours', 0);
        foreach (['store.created', 'store.updated', 'store.working-hours-updated', 'store.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'entity_id' => $id]);
        }
    }

    public function test_store_authorization_and_schedule_validation(): void
    {
        $manager = $this->userWithRole('content-manager');
        $analyst = $this->userWithRole('analyst');
        $this->actingAs($analyst)->getJson('/api/v1/admin/stores')->assertForbidden();
        $this->actingAs($analyst)->postJson('/api/v1/admin/stores', ['name' => 'A', 'address' => 'B'])->assertForbidden();
        $this->actingAs($manager)->postJson('/api/v1/admin/stores', ['name' => '', 'address' => ''])
            ->assertUnprocessable();
        $id = $this->actingAs($manager)->postJson('/api/v1/admin/stores', ['name' => 'A', 'address' => 'B'])
            ->assertCreated()->json('data.id');
        $this->actingAs($analyst)->putJson("/api/v1/admin/stores/{$id}/working-hours", ['working_hours' => []])
            ->assertForbidden();
        $this->actingAs($manager)->putJson("/api/v1/admin/stores/{$id}/working-hours", [
            'working_hours' => array_fill(0, 7, ['weekday' => 1, 'is_closed' => true]),
        ])->assertUnprocessable();
        $bad = array_map(static fn (int $day): array => [
            'weekday' => $day, 'is_closed' => false, 'opens_at' => '18:00', 'closes_at' => '09:00',
        ], range(1, 7));
        $this->actingAs($manager)->putJson("/api/v1/admin/stores/{$id}/working-hours", ['working_hours' => $bad])
            ->assertUnprocessable();
        $closedWithTime = array_map(static fn (int $day): array => [
            'weekday' => $day, 'is_closed' => true, 'opens_at' => '09:00', 'closes_at' => null,
        ], range(1, 7));
        $this->actingAs($manager)->putJson("/api/v1/admin/stores/{$id}/working-hours", ['working_hours' => $closedWithTime])
            ->assertUnprocessable();
        $this->assertDatabaseCount('store_working_hours', 7);
        $this->actingAs($manager)->getJson("/api/v1/admin/stores/{$id}")->assertOk()
            ->assertJsonPath('data.working_hours.0.is_closed', true);
    }

    private function userWithRole(string $slug): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->firstOrFail());

        return $user;
    }
}
