<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAttributeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_replace_a_category_attribute_set(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $material = Attribute::factory()->create(['name' => 'Material', 'slug' => 'material']);
        $surface = Attribute::factory()->create(['name' => 'Surface', 'slug' => 'surface']);

        $this->actingAs($actor)->putJson("/api/v1/admin/categories/{$category->id}/attributes", [
            'attributes' => [
                ['id' => $surface->id, 'sort_order' => 20],
                ['id' => $material->id, 'sort_order' => 10],
            ],
        ])->assertOk()
            ->assertJsonPath('data.0.id', $material->id)
            ->assertJsonPath('data.0.category_sort_order', 10);

        $this->actingAs($actor)->getJson("/api/v1/admin/categories/{$category->id}/attributes")
            ->assertOk()
            ->assertJsonPath('data.1.id', $surface->id);

        $this->actingAs($actor)->putJson("/api/v1/admin/categories/{$category->id}/attributes", [
            'attributes' => [['id' => $surface->id]],
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->assertDatabaseMissing('category_attribute', ['category_id' => $category->id, 'attribute_id' => $material->id]);
        $this->assertDatabaseHas('category_attribute', ['category_id' => $category->id, 'attribute_id' => $surface->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.attributes-updated', 'entity_id' => $category->id]);
    }

    public function test_category_attributes_must_be_unique_existing_attributes(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create();

        $this->actingAs($actor)->putJson("/api/v1/admin/categories/{$category->id}/attributes", [
            'attributes' => [['id' => $attribute->id], ['id' => $attribute->id]],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');

        $this->actingAs($this->userWithRole('analyst'))->getJson("/api/v1/admin/categories/{$category->id}/attributes")
            ->assertForbidden();
    }

    public function test_catalog_manager_can_assign_groups_and_then_select_their_attributes(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $group = AttributeGroup::factory()->create();
        $attribute = Attribute::factory()->create(['attribute_group_id' => $group->id]);

        $this->actingAs($actor)->putJson("/api/v1/admin/categories/{$category->id}/attribute-groups", [
            'attribute_groups' => [['id' => $group->id]],
        ])->assertOk()->assertJsonPath('data.0.id', $group->id);

        $this->actingAs($actor)->putJson("/api/v1/admin/categories/{$category->id}/attributes", [
            'attributes' => [['id' => $attribute->id]],
        ])->assertOk()->assertJsonPath('data.0.id', $attribute->id);
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
