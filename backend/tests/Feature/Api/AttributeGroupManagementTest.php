<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_manage_attribute_groups(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $created = $this->actingAs($actor)->postJson('/api/v1/admin/attribute-groups', ['name' => 'Dimensions', 'slug' => 'dimensions', 'description' => 'Product dimensions.', 'sort_order' => 10])
            ->assertCreated()->assertJsonPath('data.slug', 'dimensions');
        $id = $created->json('data.id');

        $this->actingAs($actor)->getJson('/api/v1/admin/attribute-groups')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($actor)->patchJson("/api/v1/admin/attribute-groups/{$id}", ['name' => 'Size', 'slug' => 'size'])->assertOk()->assertJsonPath('data.name', 'Size');
        $this->actingAs($actor)->deleteJson("/api/v1/admin/attribute-groups/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('attribute_groups', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attribute-group.created', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attribute-group.updated', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'attribute-group.deleted', 'entity_id' => $id]);
    }

    public function test_analyst_cannot_access_attribute_groups(): void
    {
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/attribute-groups')->assertForbidden();
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
