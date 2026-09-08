<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_manager_can_read_ordered_status_history_with_actor_snapshots(): void
    {
        $manager = $this->userWithRole('order-manager');
        $order = Order::factory()->create();

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk();
        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk();

        $this->actingAs($manager)
            ->getJson("/api/v1/admin/orders/{$order->id}/status-history?per_page=1")
            ->assertOk()
            ->assertJsonPath('data.0.from_status', 'new')
            ->assertJsonPath('data.0.to_status', 'processing')
            ->assertJsonPath('data.0.actor.id', $manager->id)
            ->assertJsonPath('data.0.actor.name', $manager->name)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_no_op_status_update_does_not_create_a_history_record_and_order_access_is_required(): void
    {
        $manager = $this->userWithRole('order-manager');
        $analyst = $this->userWithRole('analyst');
        $order = Order::factory()->create();

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'new'])
            ->assertOk();

        $this->assertDatabaseCount('order_status_histories', 0);
        $this->actingAs($analyst)
            ->getJson("/api/v1/admin/orders/{$order->id}/status-history")
            ->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
