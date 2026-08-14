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
            'is_parent' => false,
            'is_active' => true,
            'sort_order' => 20,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'ceramic-tile')
            ->assertJsonPath('data.is_parent', false)
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

    public function test_catalog_manager_can_receive_the_plumbing_category_tree(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $plumbing = Category::factory()->create(['name' => 'Сантехника', 'slug' => 'santekhnika', 'sort_order' => 10]);
        $sinks = $this->actingAs($actor)->postJson('/api/v1/admin/categories', ['parent_id' => $plumbing->id, 'name' => 'Раковины', 'slug' => 'rakoviny', 'sort_order' => 10])->assertCreated()->json('data.id');
        $tubs = $this->actingAs($actor)->postJson('/api/v1/admin/categories', ['parent_id' => $plumbing->id, 'name' => 'Ванны', 'slug' => 'vanny', 'sort_order' => 20])->assertCreated()->json('data.id');
        $toilets = $this->actingAs($actor)->postJson('/api/v1/admin/categories', ['parent_id' => $plumbing->id, 'name' => 'Унитазы', 'slug' => 'unitazy', 'sort_order' => 30])->assertCreated()->json('data.id');

        $this->actingAs($actor)->getJson('/api/v1/admin/categories/tree')
            ->assertOk()
            ->assertJsonPath('data.0.id', $plumbing->id)
            ->assertJsonPath('data.0.name', 'Сантехника')
            ->assertJsonPath('data.0.children.0.id', $sinks)
            ->assertJsonPath('data.0.children.0.name', 'Раковины')
            ->assertJsonPath('data.0.children.1.id', $tubs)
            ->assertJsonPath('data.0.children.1.name', 'Ванны')
            ->assertJsonPath('data.0.children.2.id', $toilets)
            ->assertJsonPath('data.0.children.2.name', 'Унитазы');
    }

    public function test_category_cannot_be_moved_inside_itself_or_a_descendant(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$root->id}", ['parent_id' => $root->id])->assertUnprocessable();
        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$root->id}", ['parent_id' => $child->id])->assertUnprocessable();
    }

    public function test_only_parent_categories_can_be_selected_as_a_parent(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $nonParent = Category::factory()->create(['is_parent' => false]);
        $parent = Category::factory()->create(['is_parent' => true]);
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($actor)->postJson('/api/v1/admin/categories', [
            'parent_id' => $nonParent->id,
            'name' => 'Child category',
            'slug' => 'child-category',
        ])->assertUnprocessable()->assertJsonPath('error.details.parent_id.0', 'The selected category cannot be a parent category.');

        $this->actingAs($actor)->patchJson("/api/v1/admin/categories/{$parent->id}", ['is_parent' => false])
            ->assertUnprocessable()->assertJsonPath('error.details.is_parent.0', 'A category with children must remain a parent category.');
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
