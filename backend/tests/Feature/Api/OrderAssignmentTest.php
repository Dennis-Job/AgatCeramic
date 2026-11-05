<?php

namespace Tests\Feature\Api;

use App\Enums\AdminUserStatus;
use App\Http\Resources\OrderResource;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OrderAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class OrderAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_assign_reassign_and_unassign_with_minimal_audit_and_idempotent_retries(): void
    {
        $manager = $this->employee(['orders.view', 'orders.manage']);
        $first = $this->employee(['orders.manage']);
        $second = $this->employee(['orders.manage']);
        $order = Order::factory()->create();

        $this->actingAs($manager)->patchJson("/api/v1/admin/orders/{$order->id}/assignee", ['assignee_id' => (string) $first->id])->assertNoContent();
        $this->assertSame($first->id, $order->fresh()->assignee_id);
        $assignedAt = $order->fresh()->assigned_at->toAtomString();
        $this->travel(5)->minutes();
        $this->patchJson("/api/v1/admin/orders/{$order->id}/assignee", ['assignee_id' => $first->id])->assertNoContent();
        $this->assertSame($assignedAt, $order->fresh()->assigned_at->toAtomString());
        $this->assertSame(1, AuditLog::query()->where('action', 'order.assignee-changed')->count());

        $this->patchJson("/api/v1/admin/orders/{$order->id}/assignee", ['assignee_id' => $second->id])->assertNoContent();
        $this->assertSame($second->id, $order->fresh()->assignee_id);
        $this->assertNotSame($assignedAt, $order->fresh()->assigned_at->toAtomString());
        $this->patchJson("/api/v1/admin/orders/{$order->id}/assignee", ['assignee_id' => null])->assertNoContent();
        $this->patchJson("/api/v1/admin/orders/{$order->id}/assignee", ['assignee_id' => null])->assertNoContent();
        $this->assertNull($order->fresh()->assignee_id);
        $this->assertNull($order->fresh()->assigned_at);
        $logs = AuditLog::query()->where('action', 'order.assignee-changed')->orderBy('id')->get();
        $this->assertCount(3, $logs);
        $this->assertSame([
            ['from_assignee_id' => null, 'to_assignee_id' => $first->id],
            ['from_assignee_id' => $first->id, 'to_assignee_id' => $second->id],
            ['from_assignee_id' => $second->id, 'to_assignee_id' => null],
        ], $logs->pluck('metadata')->all());
        $this->assertSame($manager->id, $logs[0]->actor_id);
        $this->assertSame(Order::class, $logs[0]->entity_type);
        $this->assertSame($order->id, $logs[0]->entity_id);
    }

    public function test_assignment_requires_manage_permission_and_active_eligible_target(): void
    {
        $manager = $this->employee(['orders.manage']);
        $viewer = $this->employee(['orders.view']);
        $blocked = $this->employee(['orders.manage']);
        $blocked->update(['status' => AdminUserStatus::Blocked]);
        $order = Order::factory()->create();

        $this->getJson('/api/v1/admin/order-assignees')->assertUnauthorized();
        $this->patchJson("/api/v1/admin/orders/{$order->id}/assignee", ['assignee_id' => null])->assertUnauthorized();
        $this->actingAs($viewer)->getJson('/api/v1/admin/order-assignees')->assertForbidden();
        $this->patchJson("/api/v1/admin/orders/{$order->id}/assignee", ['assignee_id' => $manager->id])->assertForbidden();
        $this->actingAs($manager);
        foreach ([[], ['assignee_id' => 'invalid'], ['assignee_id' => 1.5], ['assignee_id' => 999999], ['assignee_id' => $blocked->id], ['assignee_id' => $viewer->id]] as $payload) {
            $this->patchJson("/api/v1/admin/orders/{$order->id}/assignee", $payload)->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['assignee_id']]]);
        }
        $this->patchJson('/api/v1/admin/orders/999999/assignee', ['assignee_id' => null])->assertNotFound();
        $this->assertNull($order->fresh()->assignee_id);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_candidate_catalogue_is_sorted_minimal_and_excludes_blocked_or_ineligible_staff(): void
    {
        $manager = $this->employee(['orders.manage'], 'Bravo');
        $first = $this->employee(['orders.manage'], 'Alpha');
        $this->employee(['orders.view'], 'Viewer');
        $blocked = $this->employee(['orders.manage'], 'Blocked');
        $blocked->update(['status' => AdminUserStatus::Blocked]);

        $this->actingAs($manager)->getJson('/api/v1/admin/order-assignees')->assertOk()->assertExactJson(['data' => [
            ['id' => $first->id, 'name' => 'Alpha'], ['id' => $manager->id, 'name' => 'Bravo'],
        ]]);
    }

    public function test_assignment_service_rechecks_targets_after_the_candidate_list_changes(): void
    {
        $manager = $this->employee(['orders.manage']);
        $target = $this->employee(['orders.manage']);
        $order = Order::factory()->create();
        $target->roles()->detach();

        try {
            $this->app->make(OrderAssignmentService::class)->assign($manager, $order, $target->id);
            $this->fail('An employee whose permissions were revoked must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('assignee_id', $exception->errors());
        }
        $this->assertNull($order->fresh()->assignee_id);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_assignment_reloads_stale_order_state_before_audit_and_no_op_check(): void
    {
        $manager = $this->employee(['orders.manage']);
        $target = $this->employee(['orders.manage']);
        $staleOrder = Order::factory()->create();
        $service = $this->app->make(OrderAssignmentService::class);
        $service->assign($manager, $staleOrder, $manager->id);
        $service->assign($manager, $staleOrder, $target->id);
        $service->assign($manager, $staleOrder, $target->id);

        $logs = AuditLog::query()->where('action', 'order.assignee-changed')->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['from_assignee_id' => $manager->id, 'to_assignee_id' => $target->id], $logs[1]->metadata);
        $this->assertSame($target->id, $staleOrder->fresh()->assignee_id);
    }

    public function test_audit_failure_rolls_back_the_assignment(): void
    {
        $manager = $this->employee(['orders.manage']);
        $order = Order::factory()->create();
        $this->partialMock(AuditLogService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('record')->andThrow(new RuntimeException('Simulated audit write failure.'));
        });

        try {
            $this->app->make(OrderAssignmentService::class)->assign($manager, $order, $manager->id);
            $this->fail('The audit failure must abort assignment.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated audit write failure.', $exception->getMessage());
        }

        $this->assertNull($order->fresh()->assignee_id);
        $this->assertNull($order->fresh()->assigned_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_list_and_detail_include_assignee_without_exposing_it_in_public_resource(): void
    {
        $viewer = $this->employee(['orders.view']);
        $manager = $this->employee(['orders.manage'], 'Assigned employee');
        $order = Order::factory()->create(['assignee_id' => $manager->id, 'assigned_at' => now()]);
        $unassigned = Order::factory()->create();

        $this->actingAs($viewer)->getJson('/api/v1/admin/orders?assignee_id='.$manager->id)->assertOk()
            ->assertJsonPath('data.0.assignee', ['id' => $manager->id, 'name' => 'Assigned employee'])
            ->assertJsonPath('data.0.assigned_at', $order->assigned_at->toAtomString());
        $this->getJson("/api/v1/admin/orders/{$order->id}")->assertOk()
            ->assertJsonPath('data.assignee', ['id' => $manager->id, 'name' => 'Assigned employee']);
        $this->getJson("/api/v1/admin/orders/{$unassigned->id}")->assertOk()
            ->assertJsonPath('data.assignee', null)->assertJsonPath('data.assigned_at', null);
        $public = (new OrderResource($order->load('assignee')))->resolve();
        $this->assertArrayNotHasKey('assignee', $public);
        $this->assertArrayNotHasKey('assignee_id', $public);
        $this->assertArrayNotHasKey('assigned_at', $public);
    }

    public function test_assignment_filters_combine_with_search_statuses_payment_and_pagination(): void
    {
        $viewer = $this->employee(['orders.view']);
        $manager = $this->employee(['orders.manage']);
        Order::factory()->count(3)->create(['assignee_id' => $manager->id, 'status' => 'processing', 'payment_status' => 'paid', 'customer_name' => 'Matching']);
        Order::factory()->create(['assignee_id' => $manager->id, 'status' => 'cancelled', 'payment_status' => 'paid', 'customer_name' => 'Matching']);
        $unassigned = Order::factory()->create(['customer_name' => 'Matching']);
        $this->actingAs($viewer)->getJson('/api/v1/admin/orders?'.http_build_query([
            'assignee_id' => $manager->id, 'statuses' => ['new', 'processing'], 'payment_status' => 'paid', 'search' => 'Matching', 'per_page' => 2, 'page' => 2,
        ]))->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('meta.current_page', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/orders?unassigned=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unassigned->id);
        $this->getJson('/api/v1/admin/orders?unassigned=0')->assertOk()->assertJsonPath('meta.total', 5);
        foreach (['assignee_id=invalid', 'assignee_id=999999', 'unassigned=invalid'] as $query) {
            $this->getJson('/api/v1/admin/orders?'.$query)->assertUnprocessable();
        }
    }

    public function test_deleting_employee_preserves_order_and_clears_assignee_foreign_key(): void
    {
        $manager = $this->employee(['orders.manage']);
        $order = Order::factory()->create(['assignee_id' => $manager->id, 'assigned_at' => now()]);
        $manager->delete();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'assignee_id' => null]);
    }

    /** @param list<string> $permissions */
    private function employee(array $permissions, string $name = 'Employee'): User
    {
        $role = Role::factory()->create();
        foreach ($permissions as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['name' => $code]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create(['name' => $name]);
        $user->roles()->attach($role);

        return $user;
    }
}
