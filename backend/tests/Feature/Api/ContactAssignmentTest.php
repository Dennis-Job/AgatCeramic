<?php

namespace Tests\Feature\Api;

use App\Enums\AdminUserStatus;
use App\Models\ContactRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_manager_can_assign_and_unassign_an_eligible_employee(): void
    {
        $manager = $this->userWithRole('order-manager');
        $assignee = $this->userWithRole('order-manager');
        $request = ContactRequest::factory()->create();

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignee", ['assignee_id' => $assignee->id])
            ->assertNoContent();

        $this->assertDatabaseHas('contact_requests', ['id' => $request->id, 'assignee_id' => $assignee->id]);
        $this->assertNotNull($request->fresh()->assigned_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $manager->id,
            'action' => 'contact.assignee-changed',
            'entity_type' => ContactRequest::class,
            'entity_id' => $request->id,
        ]);

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignee", ['assignee_id' => null])
            ->assertNoContent();

        $this->assertDatabaseHas('contact_requests', ['id' => $request->id, 'assignee_id' => null, 'assigned_at' => null]);
    }

    public function test_assignee_must_be_an_active_contact_manager_and_assignment_requires_permission(): void
    {
        $manager = $this->userWithRole('order-manager');
        $analyst = $this->userWithRole('analyst');
        $blockedManager = $this->userWithRole('order-manager');
        $blockedManager->update(['status' => AdminUserStatus::Blocked]);
        $request = ContactRequest::factory()->create();

        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignee", ['assignee_id' => $analyst->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.assignee_id.0', 'Ответственным можно назначить только активного сотрудника с правом управления обращениями.');
        $this->actingAs($manager)
            ->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignee", ['assignee_id' => $blockedManager->id])
            ->assertUnprocessable();
        $this->actingAs($analyst)
            ->patchJson("/api/v1/admin/contact-requests/{$request->id}/assignee", ['assignee_id' => $manager->id])
            ->assertForbidden();
    }

    public function test_contact_manager_can_list_only_active_eligible_assignees(): void
    {
        $manager = $this->userWithRole('order-manager');
        $this->userWithRole('order-manager');
        $blocked = $this->userWithRole('order-manager');
        $blocked->update(['status' => AdminUserStatus::Blocked]);

        $this->actingAs($manager)->getJson('/api/v1/admin/contact-assignees')
            ->assertOk()
            ->assertJsonPath('data.0.id', $manager->id)
            ->assertJsonMissing(['id' => $blocked->id]);
    }

    private function userWithRole(string $slug): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
