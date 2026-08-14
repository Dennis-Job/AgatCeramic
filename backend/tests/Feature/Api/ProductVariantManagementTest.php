<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_manage_product_variants(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();

        $created = $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/variants", [
            'name' => '60 × 60, matte', 'sku' => 'MARBLE-WHITE-6060-MATTE', 'price' => '1990.00',
            'old_price' => '2490.00', 'stock_quantity' => 12, 'sort_order' => 10,
        ])->assertCreated()->assertJsonPath('data.product_id', $product->id)->assertJsonPath('data.price', '1990.00');
        $id = $created->json('data.id');

        $this->actingAs($actor)->getJson("/api/v1/admin/products/{$product->id}/variants")
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.per_page', 100);
        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$product->id}/variants/{$id}", [
            'price' => '1890.00', 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.old_price', '2490.00')->assertJsonPath('data.is_active', false);
        $this->actingAs($actor)->deleteJson("/api/v1/admin/products/{$product->id}/variants/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('product_variants', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.variant-created', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.variant-updated', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.variant-deleted', 'entity_id' => $id]);
    }

    public function test_product_variant_validation_authorization_and_parent_scope_are_enforced(): void
    {
        $product = Product::factory()->create();
        $this->actingAs($this->userWithRole('analyst'))
            ->getJson("/api/v1/admin/products/{$product->id}/variants")->assertForbidden();
        $actor = $this->userWithRole('catalog-manager');

        $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/variants", [
            'name' => 'Broken', 'sku' => 'bad sku', 'price' => 100, 'old_price' => 90, 'stock_quantity' => -1,
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['sku', 'old_price', 'stock_quantity']]]);

        $anotherProduct = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $anotherProduct->id]);
        $this->actingAs($actor)->getJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}")->assertNotFound();
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
