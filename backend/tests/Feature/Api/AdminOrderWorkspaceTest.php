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
            ->getJson('/api/v1/admin/orders?search='.rawurlencode('Иван').'&payment_status=pending')
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

    public function test_order_list_supports_every_documented_filter_and_pagination(): void
    {
        $viewer = $this->orderViewer();
        $matching = Order::factory()->create([
            'order_number' => 'AC-20260916-FILTER',
            'customer_name' => 'Фильтруемый покупатель',
            'customer_phone' => '+79990001122',
            'customer_email' => 'filter@example.test',
            'status' => 'processing',
            'payment_status' => PaymentStatus::Pending,
        ]);
        Order::factory()->create([
            'customer_name' => 'Другой покупатель',
            'status' => 'new',
            'payment_status' => PaymentStatus::NotPaid,
        ]);

        foreach (['FILTER', 'Фильтруемый', '0001122', 'filter@example.test'] as $search) {
            $this->actingAs($viewer)->getJson('/api/v1/admin/orders?search='.rawurlencode($search))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $matching->id);
        }

        $this->actingAs($viewer)->getJson('/api/v1/admin/orders?status=processing')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $matching->id);
        $this->actingAs($viewer)->getJson('/api/v1/admin/orders?payment_status=pending')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $matching->id);
        $this->actingAs($viewer)->getJson('/api/v1/admin/orders?per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.per_page', 1);
    }

    public function test_multiple_statuses_are_filtered_before_payment_and_pagination(): void
    {
        $this->actingAs($this->orderViewer());
        foreach (['new', 'processing', 'awaiting_clarification', 'confirmed', 'awaiting_payment', 'paid', 'picking', 'shipped', 'completed', 'cancelled'] as $status) {
            Order::factory()->create(['status' => $status, 'payment_status' => PaymentStatus::NotPaid]);
        }
        $paid = Order::factory()->create(['status' => 'picking', 'payment_status' => PaymentStatus::Paid]);
        $filter = http_build_query(['statuses' => ['processing', 'awaiting_clarification', 'confirmed', 'awaiting_payment', 'paid', 'picking']]);

        $this->getJson('/api/v1/admin/orders?'.$filter.'&per_page=2')->assertOk()
            ->assertJsonPath('meta.total', 7)->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/admin/orders?'.$filter.'&payment_status=paid')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $paid->id);
    }

    public function test_multiple_statuses_require_valid_distinct_codes_and_cannot_mix_with_single_status(): void
    {
        $this->actingAs($this->orderViewer());
        foreach ([['statuses' => 'processing'], ['statuses' => ['unknown']], ['statuses' => ['new', 'new']], ['statuses' => [['new']]], ['statuses' => ['new'], 'status' => 'new']] as $filters) {
            $this->getJson('/api/v1/admin/orders?'.http_build_query($filters))->assertUnprocessable();
        }
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
