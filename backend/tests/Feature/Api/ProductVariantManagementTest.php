<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductVariantManagementService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

    public function test_variant_can_store_only_its_distinguishing_category_attributes(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Размер', 'slug' => 'size', 'type' => 'text']);
        $color = Attribute::factory()->create(['name' => 'Цвет', 'slug' => 'color', 'type' => 'text']);
        $notAssigned = Attribute::factory()->create(['name' => 'Материал', 'slug' => 'material', 'type' => 'text']);
        $product->category->attributes()->attach([$size->id => ['sort_order' => 0], $color->id => ['sort_order' => 1]]);

        $created = $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/variants", [
            'name' => 'Белый 60 × 60', 'sku' => 'MARBLE-WHITE-6060', 'price' => '1990.00',
            'attribute_values' => [['attribute_id' => $size->id, 'value' => '60 × 60'], ['attribute_id' => $color->id, 'value' => 'Белый']],
        ])->assertCreated()->assertJsonPath('data.attribute_values.0.attribute_id', $size->id)
            ->assertJsonPath('data.attribute_values.1.value', 'Белый');

        $variantId = $created->json('data.id');
        $this->assertDatabaseHas('product_variant_attribute_values', ['product_variant_id' => $variantId, 'attribute_id' => $size->id]);

        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$product->id}/variants/{$variantId}", [
            'attribute_values' => [['attribute_id' => $color->id, 'value' => 'Серый']],
        ])->assertOk()->assertJsonCount(1, 'data.attribute_values')->assertJsonPath('data.attribute_values.0.value', 'Серый');
        $this->assertDatabaseMissing('product_variant_attribute_values', ['product_variant_id' => $variantId, 'attribute_id' => $size->id]);

        $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/variants", [
            'name' => 'Неверный вариант', 'sku' => 'MARBLE-INVALID', 'price' => '100.00',
            'attribute_values' => [['attribute_id' => $notAssigned->id, 'value' => 'Керамогранит']],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['attribute_values.0.attribute_id']]]);
    }

    public function test_service_revalidates_variant_values_against_current_options_inside_its_transaction(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();
        $attribute = Attribute::factory()->create(['type' => 'select']);
        AttributeOption::factory()->create(['attribute_id' => $attribute->id, 'value' => 'allowed']);
        $product->category->attributes()->attach($attribute->id);

        try {
            app(ProductVariantManagementService::class)->create($actor, $product, [
                'name' => 'Invalid option',
                'sku' => 'INVALID-SERVICE-OPTION',
                'price' => '100.00',
                'attribute_values' => [['attribute_id' => $attribute->id, 'value' => 'removed']],
            ]);
            $this->fail('Expected transactional semantic validation to reject the value.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attribute_values', $exception->errors());
        }

        $this->assertDatabaseMissing('product_variants', ['sku' => 'INVALID-SERVICE-OPTION']);
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
