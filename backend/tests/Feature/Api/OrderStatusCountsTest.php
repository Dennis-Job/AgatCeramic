<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_include_zeroes_for_empty_queues(): void
    {
        $this->actingAs($this->userWithRole('order-manager'))
            ->getJson('/api/v1/admin/order-status-counts')
            ->assertOk()
            ->assertExactJson(['data' => ['new' => 0, 'working' => 0, 'shipped' => 0]]);
    }

    public function test_working_counts_all_active_intermediate_stages_and_excludes_inactive_and_terminal_stages(): void
    {
        $manager = $this->userWithRole('order-manager');
        OrderStatus::factory()->create(['code' => 'custom-working']);
        OrderStatus::factory()->create(['code' => 'inactive-working', 'is_active' => false]);
        OrderStatus::factory()->create(['code' => 'custom-terminal', 'is_terminal' => true]);
        OrderStatus::factory()->create(['code' => 'inactive-terminal', 'is_active' => false, 'is_terminal' => true]);

        foreach (['new', 'new', 'processing', 'awaiting_clarification', 'confirmed', 'awaiting_payment', 'paid', 'picking', 'custom-working', 'shipped', 'completed', 'cancelled', 'inactive-working', 'custom-terminal', 'inactive-terminal'] as $status) {
            Order::factory()->create(['status' => $status]);
        }

        $this->actingAs($manager)->getJson('/api/v1/admin/order-status-counts')
            ->assertOk()
            ->assertExactJson(['data' => ['new' => 2, 'working' => 7, 'shipped' => 1]]);
    }

    public function test_counts_ignore_list_filters_pagination_and_statistics_month(): void
    {
        $manager = $this->userWithRole('order-manager');
        Order::factory()->create(['created_at' => '2025-01-15 12:00:00', 'assignee_id' => $manager->id]);
        Order::factory()->create(['status' => 'processing']);
        Order::factory()->create(['status' => 'shipped']);

        $this->actingAs($manager)
            ->getJson('/api/v1/admin/order-status-counts?status=completed&statuses[]=cancelled&payment_status=paid&search=absent&assignee_id=999999&unassigned=1&page=99&per_page=1&month=2026-11')
            ->assertOk()
            ->assertExactJson(['data' => ['new' => 1, 'working' => 1, 'shipped' => 1]]);
    }

    public function test_counts_reflect_saved_status_transitions_immediately(): void
    {
        $manager = $this->userWithRole('order-manager');
        $order = Order::factory()->create();
        $this->actingAs($manager)->getJson('/api/v1/admin/order-status-counts')
            ->assertOk()->assertExactJson(['data' => ['new' => 1, 'working' => 0, 'shipped' => 0]]);

        foreach (['processing' => ['new' => 0, 'working' => 1, 'shipped' => 0], 'shipped' => ['new' => 0, 'working' => 0, 'shipped' => 1], 'completed' => ['new' => 0, 'working' => 0, 'shipped' => 0]] as $status => $counts) {
            $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => $status])->assertOk();
            $this->getJson('/api/v1/admin/order-status-counts')->assertOk()->assertExactJson(['data' => $counts]);
        }
    }

    public function test_order_view_permission_is_sufficient_without_management_permission(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('code', 'orders.view')->sole());
        $viewer = User::factory()->create();
        $viewer->roles()->attach($role);
        $order = Order::factory()->create();

        $this->actingAs($viewer)->getJson('/api/v1/admin/order-status-counts')
            ->assertOk()->assertExactJson(['data' => ['new' => 1, 'working' => 0, 'shipped' => 0]]);
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'processing'])->assertForbidden();
    }

    public function test_counts_require_authenticated_order_view_permission(): void
    {
        $this->getJson('/api/v1/admin/order-status-counts')->assertUnauthorized();
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/order-status-counts')->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
