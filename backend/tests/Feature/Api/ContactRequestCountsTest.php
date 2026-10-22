<?php

namespace Tests\Feature\Api;

use App\Models\ContactRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactRequestCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_are_global_across_types_and_ignore_list_filters(): void
    {
        $viewer = $this->userWithRole('order-manager');
        ContactRequest::factory()->create(['status' => 'new', 'type' => 'callback']);
        ContactRequest::factory()->create(['status' => 'new', 'type' => 'partner']);
        ContactRequest::factory()->create(['status' => 'processing', 'type' => 'email', 'assignee_id' => $viewer->id]);
        ContactRequest::factory()->create(['status' => 'awaiting_client', 'type' => 'partner']);
        ContactRequest::factory()->create(['status' => 'completed']);
        ContactRequest::factory()->create(['status' => 'rejected']);

        $this->actingAs($viewer)->getJson('/api/v1/admin/contact-request-counts?status=completed&type=email&search=absent&unassigned=1&per_page=1')
            ->assertOk()->assertExactJson(['data' => ['new' => 2, 'processing' => 1, 'awaiting_client' => 1]]);
    }

    public function test_counts_include_zeroes_and_reflect_saved_transitions_immediately(): void
    {
        $manager = $this->userWithRole('order-manager');
        $this->actingAs($manager)->getJson('/api/v1/admin/contact-request-counts')
            ->assertOk()->assertExactJson(['data' => ['new' => 0, 'processing' => 0, 'awaiting_client' => 0]]);
        $contact = ContactRequest::factory()->create();
        $this->actingAs($manager)->patchJson("/api/v1/admin/contact-requests/{$contact->id}/status", ['status' => 'processing'])
            ->assertOk();
        $this->actingAs($manager)->getJson('/api/v1/admin/contact-request-counts')
            ->assertOk()->assertExactJson(['data' => ['new' => 0, 'processing' => 1, 'awaiting_client' => 0]]);
    }

    public function test_counts_require_contact_view_permission(): void
    {
        $this->getJson('/api/v1/admin/contact-request-counts')->assertUnauthorized();
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/contact-request-counts')->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
