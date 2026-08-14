<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_manage_products(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $created = $this->actingAs($actor)->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Marble White 60x60',
            'slug' => 'marble-white-60x60',
            'description' => 'Porcelain tile.',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.brand.id', $brand->id);
        $id = $created->json('data.id');

        $this->actingAs($actor)->getJson('/api/v1/admin/products')
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.per_page', 25);
        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$id}", [
            'brand_id' => null, 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.brand_id', null)->assertJsonPath('data.is_active', false);
        $this->actingAs($actor)->deleteJson("/api/v1/admin/products/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.updated', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.deleted', 'entity_id' => $id]);
    }

    public function test_product_validation_and_authorization_are_enforced(): void
    {
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/products')->assertForbidden();
        $actor = $this->userWithRole('catalog-manager');

        $this->actingAs($actor)->postJson('/api/v1/admin/products', [
            'name' => 'Product', 'slug' => 'Invalid Slug', 'category_id' => 999999,
        ])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['category_id', 'slug']]]);

        $product = Product::factory()->create(['slug' => 'existing-product']);
        $this->actingAs($actor)->postJson('/api/v1/admin/products', [
            'category_id' => $product->category_id, 'name' => 'Other product', 'slug' => 'existing-product',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
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
