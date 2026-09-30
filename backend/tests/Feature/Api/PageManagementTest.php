<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_manager_can_create_publish_edit_and_delete_a_page(): void
    {
        $actor = $this->userWithRole('content-manager');
        $created = $this->actingAs($actor)->postJson('/api/v1/admin/pages', [
            'title' => 'О компании', 'slug' => 'company', 'body' => '<script>alert(1)</script> Текст',
        ])->assertCreated()->assertJsonPath('data.is_published', false);
        $id = $created->json('data.id');

        $this->getJson('/api/v1/pages/company')->assertNotFound();
        $this->getJson('/api/v1/pages')->assertOk()->assertJsonCount(4, 'data');
        $this->actingAs($actor)->getJson('/api/v1/admin/pages')->assertOk()->assertJsonPath('data.0.id', $id);

        $this->actingAs($actor)->postJson("/api/v1/admin/pages/{$id}/publish")
            ->assertOk()->assertJsonPath('data.is_published', true);
        $this->getJson('/api/v1/pages/company')->assertOk()->assertJsonPath('data.body', '<script>alert(1)</script> Текст');
        $this->getJson('/api/v1/pages')->assertOk()->assertJsonPath('data.4.slug', 'company');

        $this->actingAs($actor)->patchJson("/api/v1/admin/pages/{$id}", ['is_published' => false])->assertOk();
        $this->getJson('/api/v1/pages/company')->assertNotFound();
        $this->actingAs($actor)->deleteJson("/api/v1/admin/pages/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('pages', ['id' => $id]);
        foreach (['page.created', 'page.updated', 'page.deleted'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'entity_id' => $id]);
        }
    }

    public function test_page_validation_and_permissions_are_enforced(): void
    {
        $manager = $this->userWithRole('content-manager');
        $analyst = $this->userWithRole('analyst');
        $this->actingAs($analyst)->getJson('/api/v1/admin/pages')->assertForbidden();
        $this->actingAs($analyst)->postJson('/api/v1/admin/pages', ['title' => 'A', 'slug' => 'a', 'body' => 'A'])->assertForbidden();

        $this->actingAs($manager)->postJson('/api/v1/admin/pages', ['title' => 'A', 'slug' => 'Bad Slug', 'body' => 'A'])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['slug']]]);
        $created = $this->actingAs($manager)->postJson('/api/v1/admin/pages', ['title' => 'A', 'slug' => 'company', 'body' => 'A'])->assertCreated();
        $id = $created->json('data.id');
        $this->actingAs($manager)->postJson('/api/v1/admin/pages', ['title' => 'B', 'slug' => 'company', 'body' => 'B'])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['slug']]]);
        $this->actingAs($manager)->patchJson("/api/v1/admin/pages/{$id}", ['body' => ['invalid']])->assertUnprocessable();
        $this->actingAs($analyst)->patchJson("/api/v1/admin/pages/{$id}", ['title' => 'No'])->assertForbidden();
        $this->actingAs($analyst)->deleteJson("/api/v1/admin/pages/{$id}")->assertForbidden();
    }

    private function userWithRole(string $slug): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
