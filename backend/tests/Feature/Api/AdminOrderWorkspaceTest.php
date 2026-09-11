<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_viewer_can_filter_the_list_and_read_the_complete_snapshot(): void
    {
        $viewer = $this->orderViewer();
        $matching = Order::factory()->create([
            'customer_name' => 'Иван Петров',
            'customer_email' => 'ivan@example.test',
            'delivery_address' => 'Москва, ул. Пример, 1',
            'customer_comment' => 'Позвонить за час',
            'payment_status' => PaymentStatus::Pending,
        ]);
        OrderItem::factory()->for($matching)->create(['product_name' => 'Керамогранит', 'quantity' => 2]);
        Order::factory()->create(['customer_name' => 'Другой покупатель', 'status' => 'processing']);

        $this->actingAs($viewer)
            ->getJson('/api/v1/admin/orders?search=Иван&payment_status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.customer.name', 'Иван Петров')
            ->assertJsonPath('data.0.items.0.product_name', 'Керамогранит');

        $this->actingAs($viewer)
            ->getJson("/api/v1/admin/orders/{$matching->id}")
            ->assertOk()
            ->assertJsonPath('data.delivery_address', 'Москва, ул. Пример, 1')
            ->assertJsonPath('data.customer_comment', 'Позвонить за час')
            ->assertJsonPath('data.customer.email', 'ivan@example.test')
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_order_workspace_requires_order_view_permission(): void
    {
        $order = Order::factory()->create();
        $unauthorized = User::factory()->create();

        $this->actingAs($unauthorized)->getJson('/api/v1/admin/orders')->assertForbidden();
        $this->actingAs($unauthorized)->getJson("/api/v1/admin/orders/{$order->id}")->assertForbidden();
    }

    private function orderViewer(): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('code', 'orders.view')->sole());
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
