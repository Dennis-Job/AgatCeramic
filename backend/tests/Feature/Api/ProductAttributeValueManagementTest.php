<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAttributeValueManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_replace_product_attribute_values(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $material = Attribute::factory()->create(['type' => 'select', 'is_required' => true]);
        AttributeOption::factory()->create(['attribute_id' => $material->id, 'value' => 'porcelain', 'label' => 'Porcelain']);
        $thickness = Attribute::factory()->create(['type' => 'number', 'unit' => 'mm']);
        $category->attributes()->attach([$material->id => ['sort_order' => 0], $thickness->id => ['sort_order' => 1]]);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}/attributes", [
            'attributes' => [
                ['attribute_id' => $material->id, 'value' => 'porcelain'],
                ['attribute_id' => $thickness->id, 'value' => 9.5],
            ],
        ])->assertOk()->assertJsonPath('data.0.attribute_id', $material->id)
            ->assertJsonPath('data.0.value', 'porcelain')->assertJsonPath('data.1.value', 9.5);

        $this->actingAs($actor)->getJson("/api/v1/admin/products/{$product->id}/attributes")
            ->assertOk()->assertJsonPath('data.0.attribute.options.0.value', 'porcelain');

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}/attributes", [
            'attributes' => [['attribute_id' => $material->id, 'value' => 'porcelain']],
        ])->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseMissing('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $thickness->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.attributes-updated', 'entity_id' => $product->id]);
    }

    public function test_values_must_match_the_category_attribute_definition(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $required = Attribute::factory()->create(['type' => 'boolean', 'is_required' => true]);
        $unassigned = Attribute::factory()->create(['type' => 'text']);
        $category->attributes()->attach($required->id);
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}/attributes", [
            'attributes' => [['attribute_id' => $unassigned->id, 'value' => 'anything']],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['attributes.0.attribute_id', 'attributes']]]);

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}/attributes", [
            'attributes' => [['attribute_id' => $required->id, 'value' => 'true']],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['attributes.0.value']]]);
        $this->actingAs($this->userWithRole('analyst'))->getJson("/api/v1/admin/products/{$product->id}/attributes")->assertForbidden();
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
