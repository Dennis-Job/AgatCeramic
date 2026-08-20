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
            'article_number' => ' AC-6060-M ', 'barcode' => '04601234567890', 'unit' => 'square_meter',
            'old_price' => '2490.00', 'stock_quantity' => 12, 'sort_order' => 10,
        ])->assertCreated()->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.article_number', 'AC-6060-M')->assertJsonPath('data.barcode', '04601234567890')
            ->assertJsonPath('data.unit', 'square_meter')->assertJsonPath('data.price', '1990.00');
        $id = $created->json('data.id');

        $this->actingAs($actor)->getJson("/api/v1/admin/products/{$product->id}/variants")
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.per_page', 100);
        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$product->id}/variants/{$id}", [
            'price' => '1890.00', 'unit' => 'package', 'barcode' => null, 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.old_price', '2490.00')->assertJsonPath('data.unit', 'package')
            ->assertJsonPath('data.barcode', null)->assertJsonPath('data.is_active', false);
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
            'name' => 'Broken', 'sku' => 'bad sku', 'barcode' => 'ABC123', 'unit' => 'box',
            'price' => 100, 'old_price' => 90, 'stock_quantity' => -1,
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['sku', 'barcode', 'unit', 'old_price', 'stock_quantity']]]);

        $anotherProduct = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $anotherProduct->id]);
        $this->actingAs($actor)->getJson("/api/v1/admin/products/{$product->id}/variants/{$variant->id}")->assertNotFound();
    }

    public function test_variant_identifiers_are_nullable_but_globally_unique_and_unit_is_required(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();
        $existing = ProductVariant::factory()->create([
            'article_number' => 'VENDOR-100',
            'barcode' => '0123456789012',
            'unit' => 'piece',
        ]);

        $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/variants", [
            'name' => 'Missing unit', 'sku' => 'MISSING-UNIT', 'price' => '100.00',
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['unit']]]);

        $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/variants", [
            'name' => 'Duplicate identifiers', 'sku' => 'DUPLICATE-IDENTIFIERS', 'price' => '100.00',
            'article_number' => $existing->article_number, 'barcode' => $existing->barcode, 'unit' => 'piece',
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['article_number', 'barcode']]]);

        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$existing->product_id}/variants/{$existing->id}", [
            'article_number' => $existing->article_number, 'barcode' => $existing->barcode, 'unit' => 'liter',
        ])->assertOk()->assertJsonPath('data.unit', 'liter');

        $created = $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/variants", [
            'name' => 'No external identifiers', 'sku' => 'NO-EXTERNAL-ID', 'price' => '100.00',
            'article_number' => '   ', 'barcode' => '', 'unit' => 'set',
        ])->assertCreated()->assertJsonPath('data.article_number', null)->assertJsonPath('data.barcode', null);

        $this->assertDatabaseHas('product_variants', ['id' => $created->json('data.id'), 'unit' => 'set']);
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
            'name' => 'Белый 60 × 60', 'sku' => 'MARBLE-WHITE-6060', 'unit' => 'square_meter', 'price' => '1990.00',
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
            'name' => 'Неверный вариант', 'sku' => 'MARBLE-INVALID', 'unit' => 'piece', 'price' => '100.00',
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
