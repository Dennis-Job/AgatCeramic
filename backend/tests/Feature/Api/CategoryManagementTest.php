<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_create_list_update_and_delete_a_category(): void
    {
        $actor = $this->userWithRole('catalog-manager');

        $created = $this->actingAs($actor)->postJson('/api/v1/admin/categories', [
            'name' => 'Ceramic tile',
            'slug' => 'ceramic-tile',
            'description' => 'Wall and floor ceramic tile.',
            'is_active' => true,
            'sort_order' => 20,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'ceramic-tile')
            ->assertJsonPath('data.is_active', true);

        $categoryId = $created->json('data.id');

        $this->actingAs($actor)->getJson('/api/v1/admin/categories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $categoryId)
            ->assertJsonPath('meta.per_page', 25);

        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$categoryId}", [
            'is_active' => false,
            'sort_order' => 10,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sort_order', 10);

        $this->actingAs($actor)->deleteJson("/api/v1/admin/categories/{$categoryId}")->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $categoryId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.created', 'entity_id' => $categoryId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.updated', 'entity_id' => $categoryId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.deleted', 'entity_id' => $categoryId]);
    }

    public function test_category_slug_must_be_unique_and_url_safe(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        Category::factory()->create(['slug' => 'ceramic-tile']);

        $this->actingAs($actor)->postJson('/api/v1/admin/categories', [
            'name' => 'Duplicate category',
            'slug' => 'Ceramic Tile',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');

        $this->actingAs($actor)->postJson('/api/v1/admin/categories', [
            'name' => 'Duplicate category',
            'slug' => 'ceramic-tile',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_administrator_cannot_access_categories(): void
    {
        $actor = $this->userWithRole('analyst');

        $this->actingAs($actor)->getJson('/api/v1/admin/categories')->assertForbidden();
    }

    public function test_catalog_manager_can_receive_a_nested_category_tree(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $root = Category::factory()->create(['name' => 'Root', 'sort_order' => 10]);
        $child = Category::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

        $this->actingAs($actor)->getJson('/api/v1/admin/categories/tree')
            ->assertOk()
            ->assertJsonPath('data.0.id', $root->id)
            ->assertJsonPath('data.0.children.0.id', $child->id)
            ->assertJsonPath('data.0.children.0.children.0.id', $grandchild->id);
    }

    public function test_category_cannot_be_moved_inside_itself_or_a_descendant(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$root->id}", ['parent_id' => $root->id])->assertUnprocessable();
        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$root->id}", ['parent_id' => $child->id])->assertUnprocessable();
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
