<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_manager_can_add_an_immutable_internal_comment_and_order_viewer_can_read_it(): void
    {
        $manager = $this->userWithRole('order-manager');
        $viewer = $this->orderViewer();
        $order = Order::factory()->create();

        $this->actingAs($manager)
            ->postJson("/api/v1/admin/orders/{$order->id}/comments", ['body' => '  Клиент просит позвонить перед доставкой.  '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Клиент просит позвонить перед доставкой.')
            ->assertJsonPath('data.author.id', $manager->id)
            ->assertJsonPath('data.author.name', $manager->name);

        $this->actingAs($viewer)
            ->getJson("/api/v1/admin/orders/{$order->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Клиент просит позвонить перед доставкой.')
            ->assertJsonPath('data.0.author.name', $manager->name);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $manager->id,
            'action' => 'order.comment-added',
            'entity_type' => Order::class,
            'entity_id' => $order->id,
        ]);
    }

    public function test_comment_requires_order_management_and_non_blank_text(): void
    {
        $manager = $this->userWithRole('order-manager');
        $viewer = $this->orderViewer();
        $order = Order::factory()->create();

        $this->actingAs($viewer)
            ->postJson("/api/v1/admin/orders/{$order->id}/comments", ['body' => 'Нельзя добавить'])
            ->assertForbidden();

        $this->actingAs($manager)
            ->postJson("/api/v1/admin/orders/{$order->id}/comments", ['body' => '   '])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['body']]]);

        $this->assertDatabaseCount('order_comments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function userWithRole(string $slug): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
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
