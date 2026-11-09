<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_manager_can_view_the_server_managed_status_catalog(): void
    {
        $manager = $this->userWithRole('order-manager');

        $this->actingAs($manager)
            ->getJson('/api/v1/admin/order-statuses')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.code', 'new')
            ->assertJsonPath('data.0.name', 'Новый')
            ->assertJsonPath('data.0.is_selectable', true)
            ->assertJsonPath('data.4.is_selectable', false)
            ->assertJsonPath('data.5.is_selectable', false)
            ->assertJsonPath('data.8.code', 'completed')
            ->assertJsonPath('data.8.is_terminal', true)
            ->assertJsonPath('data.9.code', 'cancelled');
    }

    public function test_order_manager_can_change_an_order_to_an_active_status_and_the_change_is_audited(): void
    {
        $manager = $this->userWithRole('order-manager');
        $order = Order::factory()->create();

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $manager->id,
            'action' => 'order.status-changed',
            'entity_type' => Order::class,
            'entity_id' => $order->id,
        ]);

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_payment_stages_cannot_be_selected_as_order_statuses(): void
    {
        $manager = $this->userWithRole('order-manager');
        $order = Order::factory()->create(['status' => 'processing']);

        foreach (['awaiting_payment', 'paid'] as $status) {
            $this->actingAs($manager)
                ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $status])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status', 'error.details');
        }

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_legacy_payment_stages_can_transition_to_an_order_stage_without_changing_payment(): void
    {
        $manager = $this->userWithRole('order-manager');

        foreach (['awaiting_payment', 'paid'] as $status) {
            $order = Order::factory()->create(['status' => $status, 'payment_status' => 'not_paid']);
            $this->actingAs($manager)
                ->getJson("/api/v1/admin/orders/{$order->id}")
                ->assertOk()
                ->assertJsonPath('data.status', $status);
            $this->actingAs($manager)
                ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'confirmed'])
                ->assertOk()
                ->assertJsonPath('data.status', 'confirmed')
                ->assertJsonPath('data.payment_status', 'not_paid');
            $this->assertDatabaseHas('order_status_histories', [
                'order_id' => $order->id,
                'from_status' => $status,
                'to_status' => 'confirmed',
            ]);
        }
    }

    public function test_terminal_order_status_cannot_be_changed_and_inactive_status_cannot_be_selected(): void
    {
        $manager = $this->userWithRole('order-manager');
        $order = Order::factory()->create(['status' => 'completed', 'completed_at' => now()]);
        OrderStatus::factory()->create(['code' => 'archived', 'is_active' => false]);

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.status.0', 'Терминальный статус заказа изменить нельзя.');

        $newOrder = Order::factory()->create();
        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/orders/{$newOrder->id}/status", ['status' => 'archived'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.status.0', 'Выбранный статус недоступен.');
    }

    public function test_user_without_order_permission_cannot_view_or_change_statuses(): void
    {
        $analyst = $this->userWithRole('analyst');
        $order = Order::factory()->create();

        $this->actingAs($analyst)->getJson('/api/v1/admin/order-statuses')->assertForbidden();
        $this->actingAs($analyst)
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])
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
