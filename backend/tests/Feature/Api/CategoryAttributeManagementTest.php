<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
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

    public function test_used_product_and_variant_attributes_cannot_be_detached_from_a_category(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $productAttribute = Attribute::factory()->create();
        $variantAttribute = Attribute::factory()->create();
        $category->attributes()->attach([$productAttribute->id, $variantAttribute->id]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $productAttribute->id, 'value' => 'porcelain']);
        ProductVariantAttributeValue::query()->create(['product_variant_id' => $variant->id, 'attribute_id' => $variantAttribute->id, 'value' => '60x60']);

        $this->actingAs($actor)->putJson("/api/v1/admin/categories/{$category->id}/attributes", [
            'attributes' => [],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['attributes']]]);

        $this->assertDatabaseHas('category_attribute', ['category_id' => $category->id, 'attribute_id' => $productAttribute->id]);
        $this->assertDatabaseHas('category_attribute', ['category_id' => $category->id, 'attribute_id' => $variantAttribute->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'category.attributes-updated', 'entity_id' => $category->id]);
    }

    public function test_group_replacement_cannot_implicitly_detach_an_attribute_that_is_in_use(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $group = AttributeGroup::factory()->create();
        $attribute = Attribute::factory()->create(['attribute_group_id' => $group->id]);
        $category->attributeGroups()->attach($group->id);
        $category->attributes()->attach($attribute->id);
        $product = Product::factory()->create(['category_id' => $category->id]);
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => 'used']);

        $this->actingAs($actor)->putJson("/api/v1/admin/categories/{$category->id}/attribute-groups", [
            'attribute_groups' => [],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['attribute_groups']]]);

        $this->assertDatabaseHas('category_attribute_group', ['category_id' => $category->id, 'attribute_group_id' => $group->id]);
        $this->assertDatabaseHas('category_attribute', ['category_id' => $category->id, 'attribute_id' => $attribute->id]);
    }

    public function test_required_attribute_can_be_assigned_before_category_product_values_are_filled(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);
        $required = Attribute::factory()->create(['is_required' => true]);

        $this->actingAs($actor)->putJson("/api/v1/admin/categories/{$category->id}/attributes", [
            'attributes' => [['id' => $required->id]],
        ])->assertOk()->assertJsonPath('data.0.id', $required->id);
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
