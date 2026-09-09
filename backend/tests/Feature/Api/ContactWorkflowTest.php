<?php

namespace Tests\Feature\Api;

use App\Models\ContactRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_viewer_can_list_filter_and_read_contact_requests_without_management_access(): void
    {
        $viewer = $this->userWithRole('order-manager');
        $callback = ContactRequest::factory()->create(['type' => 'callback', 'name' => 'Иван Петров', 'phone' => '+7 (999) 123-45-67']);
        ContactRequest::factory()->create(['type' => 'email', 'email' => 'other@example.test']);

        $this->actingAs($viewer)->getJson('/api/v1/admin/contact-statuses')
            ->assertOk()->assertJsonPath('data.0.code', 'new')->assertJsonPath('data.3.is_terminal', true);
        $this->actingAs($viewer)->getJson('/api/v1/admin/contact-requests?type=callback&search=Иван')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $callback->id)
            ->assertJsonPath('data.0.contact.phone', '+7 (999) 123-45-67');
        $this->actingAs($viewer)->getJson("/api/v1/admin/contact-requests/{$callback->id}")
            ->assertOk()->assertJsonPath('data.status', 'new');
    }

    public function test_contact_manager_can_transition_statuses_with_history_and_terminal_protection(): void
    {
        $manager = $this->userWithRole('order-manager');
        $contact = ContactRequest::factory()->create();

        $this->actingAs($manager)->patchJson("/api/v1/admin/contact-requests/{$contact->id}/status", ['status' => 'processing'])
            ->assertOk()->assertJsonPath('data.status', 'processing');
        $this->actingAs($manager)->patchJson("/api/v1/admin/contact-requests/{$contact->id}/status", ['status' => 'completed'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($contact->fresh()->completed_at);
        $this->actingAs($manager)->getJson("/api/v1/admin/contact-requests/{$contact->id}/status-history")
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.from_status', 'new')
            ->assertJsonPath('data.1.to_status', 'completed');
        $this->actingAs($manager)->patchJson("/api/v1/admin/contact-requests/{$contact->id}/status", ['status' => 'processing'])
            ->assertUnprocessable()->assertJsonPath('error.details.status.0', 'Терминальный статус обращения изменить нельзя.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact.status-changed', 'entity_id' => $contact->id]);
    }

    public function test_contact_manager_can_add_immutable_comments_and_unauthorized_user_is_forbidden(): void
    {
        $manager = $this->userWithRole('order-manager');
        $analyst = $this->userWithRole('analyst');
        $contact = ContactRequest::factory()->create();

        $this->actingAs($manager)->postJson("/api/v1/admin/contact-requests/{$contact->id}/comments", ['body' => '  Перезвонить после обеда.  '])
            ->assertCreated()->assertJsonPath('data.body', 'Перезвонить после обеда.');
        $this->actingAs($manager)->getJson("/api/v1/admin/contact-requests/{$contact->id}/comments")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.author.name', $manager->name);
        $this->actingAs($analyst)->getJson('/api/v1/admin/contact-requests')->assertForbidden();
        $this->actingAs($analyst)->postJson("/api/v1/admin/contact-requests/{$contact->id}/comments", ['body' => 'Нет доступа'])
            ->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'contact.comment-added', 'entity_id' => $contact->id]);
    }

    private function userWithRole(string $slug): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
