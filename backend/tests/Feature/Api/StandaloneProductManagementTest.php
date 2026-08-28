<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductRelation;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StandaloneProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_product_is_draft_by_default_and_activation_requires_assignment_specific_values(): void
    {
        $actor = $this->catalogManager();
        $category = Category::factory()->create();
        $required = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach($required->id, ['is_required' => true]);

        $response = $this->actingAs($actor)->postJson('/api/v1/admin/products', [
            'category_id' => $category->id, 'name' => 'White tile', 'slug' => 'white-tile',
            'unit' => 'piece', 'price' => 100, 'stock_quantity' => 4,
        ])->assertCreated()->assertJsonPath('data.is_active', false)->assertJsonPath('data.sku', '01000001');
        $productId = $response->json('data.id');

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$productId}/attributes", ['attributes' => []])->assertOk();

        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$productId}", ['is_active' => true])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['is_active']]]);
        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$productId}/attributes", [
            'attributes' => [['attribute_id' => $required->id, 'value' => 'porcelain']],
        ])->assertOk();
        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$productId}", ['is_active' => true])
            ->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_sku_uses_root_category_prefix_and_a_global_six_digit_number(): void
    {
        $actor = $this->catalogManager();
        $tileRoot = Category::factory()->create();
        $tileChild = Category::factory()->create(['parent_id' => $tileRoot->id]);
        $plumbingRoot = Category::factory()->create();

        $create = fn (Category $category, string $slug) => $this->actingAs($actor)->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => $slug,
            'slug' => $slug,
            'unit' => 'piece',
            'price' => 10,
            'stock_quantity' => 0,
        ])->assertCreated();

        $create($tileChild, 'tile-one')->assertJsonPath('data.sku', '01000001');
        $create($plumbingRoot, 'sink-one')->assertJsonPath('data.sku', '02000002');
        $create($tileRoot, 'tile-two')->assertJsonPath('data.sku', '01000003');

        $this->assertDatabaseHas('categories', ['id' => $tileChild->id, 'sku_prefix' => '01']);
        $this->assertDatabaseHas('categories', ['id' => $plumbingRoot->id, 'sku_prefix' => '02']);
    }

    public function test_product_groups_validate_axes_members_and_return_axis_values(): void
    {
        $actor = $this->catalogManager();
        $category = Category::factory()->create();
        $color = Attribute::factory()->create(['type' => 'select']);
        $material = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach([$color->id => ['is_required' => true], $material->id => ['is_required' => false]]);
        $red = Product::factory()->create(['category_id' => $category->id, 'brand_id' => null, 'sku' => 'RED']);
        $blue = Product::factory()->create(['category_id' => $category->id, 'brand_id' => null, 'sku' => 'BLUE']);
        foreach ([[$red, 'red'], [$blue, 'blue']] as [$product, $value]) {
            $product->attributeValues()->create(['attribute_id' => $color->id, 'value' => $value]);
            $product->attributeValues()->create(['attribute_id' => $material->id, 'value' => 'porcelain']);
        }
        $thumbnail = ProductImage::query()->create([
            'product_id' => $red->id, 'disk' => 'public', 'path' => "product-images/{$red->id}/thumb.jpg",
            'mime_type' => 'image/jpeg', 'size' => 10, 'alt' => 'Red tile', 'is_primary' => true,
        ]);

        $created = $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Tile colors', 'code' => 'TILE-COLORS',
            'axis_attribute_ids' => [$color->id], 'product_ids' => [$red->id, $blue->id],
        ])->assertCreated()->assertJsonPath('data.axes.0.id', $color->id)
            ->assertJsonPath('data.products.0.axis_values.0.attribute_id', $color->id)
            ->assertJsonPath('data.products.0.primary_image.id', $thumbnail->id);

        $this->actingAs($actor)->getJson("/api/v1/admin/product-groups/{$created->json('data.id')}")
            ->assertOk()->assertJsonCount(2, 'data.products');
        $this->assertDatabaseHas('product_group_members', ['product_id' => $red->id]);
        $this->actingAs($actor)->getJson("/api/v1/admin/products/{$red->id}")
            ->assertOk()->assertJsonPath('data.group.code', 'TILE-COLORS');

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$red->id}/attributes", [
            'attributes' => [
                ['attribute_id' => $color->id, 'value' => 'red'],
                ['attribute_id' => $material->id, 'value' => 'ceramic'],
            ],
        ])->assertUnprocessable();
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $red->id, 'attribute_id' => $material->id, 'value' => json_encode('porcelain'),
        ]);

        $third = Product::factory()->create(['category_id' => $category->id, 'brand_id' => null]);
        $third->attributeValues()->create(['attribute_id' => $color->id, 'value' => 'red']);
        $third->attributeValues()->create(['attribute_id' => $material->id, 'value' => 'porcelain']);
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Duplicate tuple', 'code' => 'DUPLICATE-TUPLE',
            'axis_attribute_ids' => [$color->id], 'product_ids' => [$red->id, $third->id],
        ])->assertUnprocessable();
    }

    public function test_product_groups_support_multiple_axes_and_reject_incompatible_members(): void
    {
        $actor = $this->catalogManager();
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        $color = Attribute::factory()->create(['type' => 'select']);
        $format = Attribute::factory()->create(['type' => 'string']);
        $material = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach([$color->id, $format->id, $material->id]);
        $otherCategory->attributes()->attach([$color->id, $format->id, $material->id]);
        $make = function (string $colorValue, ?string $formatValue, string $materialValue = 'porcelain', ?int $categoryId = null, ?int $brandId = null) use ($category, $color, $format, $material): Product {
            $product = Product::factory()->create(['category_id' => $categoryId ?? $category->id, 'brand_id' => $brandId]);
            $product->attributeValues()->create(['attribute_id' => $color->id, 'value' => $colorValue]);
            if ($formatValue !== null) {
                $product->attributeValues()->create(['attribute_id' => $format->id, 'value' => $formatValue]);
            }
            $product->attributeValues()->create(['attribute_id' => $material->id, 'value' => $materialValue]);

            return $product;
        };
        $redSmall = $make('red', '60x60');
        $redLarge = $make('red', '60x120');
        $group = $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Multi axis', 'code' => 'MULTI-AXIS',
            'axis_attribute_ids' => [$color->id, $format->id], 'product_ids' => [$redSmall->id, $redLarge->id],
        ])->assertCreated()->assertJsonCount(2, 'data.axes');

        $missing = $make('blue', null);
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Missing axis', 'code' => 'MISSING-AXIS',
            'axis_attribute_ids' => [$color->id, $format->id], 'product_ids' => [$missing->id, $make('green', '60x60')->id],
        ])->assertUnprocessable();
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Common mismatch', 'code' => 'COMMON-MISMATCH',
            'axis_attribute_ids' => [$color->id], 'product_ids' => [$make('black', '30x30', 'ceramic')->id, $make('white', '30x30', 'porcelain')->id],
        ])->assertUnprocessable();
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Category mismatch', 'code' => 'CATEGORY-MISMATCH',
            'axis_attribute_ids' => [$color->id], 'product_ids' => [$make('gold', '20x20')->id, $make('silver', '20x20', 'porcelain', $otherCategory->id)->id],
        ])->assertUnprocessable();
        $firstBrand = Brand::factory()->create();
        $secondBrand = Brand::factory()->create();
        $this->actingAs($actor)->postJson('/api/v1/admin/product-groups', [
            'name' => 'Brand mismatch', 'code' => 'BRAND-MISMATCH',
            'axis_attribute_ids' => [$color->id], 'product_ids' => [$make('beige', '10x10', 'porcelain', null, $firstBrand->id)->id, $make('brown', '10x10', 'porcelain', null, $secondBrand->id)->id],
        ])->assertUnprocessable();

        $this->actingAs($actor)->deleteJson("/api/v1/admin/product-groups/{$group->json('data.id')}")->assertNoContent();
        $this->assertDatabaseHas('products', ['id' => $redSmall->id]);
        $this->assertDatabaseHas('products', ['id' => $redLarge->id]);
    }

    public function test_standalone_identifiers_are_globally_unique_and_sku_is_server_owned(): void
    {
        $actor = $this->catalogManager();
        $existing = Product::factory()->create(['sku' => 'UNIQUE-SKU', 'article_number' => 'UNIQUE-ART', 'barcode' => '0123456789012']);
        $this->actingAs($actor)->postJson('/api/v1/admin/products', [
            'category_id' => $existing->category_id, 'name' => 'Duplicate', 'slug' => 'duplicate-identifiers',
            'article_number' => $existing->article_number, 'barcode' => $existing->barcode,
            'unit' => 'piece', 'price' => 10, 'stock_quantity' => 0,
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['article_number', 'barcode']]]);

        $this->actingAs($actor)->postJson('/api/v1/admin/products', [
            'category_id' => $existing->category_id, 'name' => 'Manual SKU', 'slug' => 'manual-sku',
            'sku' => '99000001', 'unit' => 'piece', 'price' => 10, 'stock_quantity' => 0,
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['sku']]]);

        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$existing->id}", ['sku' => '99000002'])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['sku']]]);
    }

    public function test_relation_candidates_exclude_self_existing_outgoing_and_reverse_pairs(): void
    {
        $actor = $this->catalogManager();
        $product = Product::factory()->create();
        $outgoing = Product::factory()->create();
        $incoming = Product::factory()->create();
        $candidate = Product::factory()->create(['name' => 'Blue candidate', 'sku' => 'BLUE-CANDIDATE']);
        ProductRelation::query()->create(['product_id' => $product->id, 'related_product_id' => $outgoing->id, 'type' => 'related']);
        ProductRelation::query()->create(['product_id' => $incoming->id, 'related_product_id' => $product->id, 'type' => 'recommended']);

        $this->actingAs($actor)->getJson("/api/v1/admin/products/{$product->id}/relation-candidates?search=blue&limit=10")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $candidate->id);
    }

    public function test_legacy_converter_is_dry_run_by_default_and_idempotently_clones_galleries(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $color = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach($color->id);
        $legacy = Product::factory()->create([
            'category_id' => $category->id, 'sku' => null, 'article_number' => null, 'barcode' => null,
            'unit' => null, 'price' => null, 'old_price' => null, 'stock_quantity' => null, 'is_active' => false,
        ]);
        Storage::disk('public')->put("product-images/{$legacy->id}/main.jpg", 'image');
        ProductImage::query()->create([
            'product_id' => $legacy->id, 'disk' => 'public', 'path' => "product-images/{$legacy->id}/main.jpg",
            'mime_type' => 'image/jpeg', 'size' => 5, 'is_primary' => true,
        ]);
        foreach (['red', 'blue'] as $index => $value) {
            $variant = ProductVariant::factory()->create(['product_id' => $legacy->id, 'sku' => strtoupper($value), 'is_active' => false]);
            ProductVariantAttributeValue::query()->create(['product_variant_id' => $variant->id, 'attribute_id' => $color->id, 'value' => $value]);
        }

        $this->artisan('catalog:migrate-standalone-products')->assertSuccessful();
        $this->assertSame(1, Product::query()->count());
        $this->artisan('catalog:migrate-standalone-products', ['--apply' => true])->assertSuccessful();
        $this->assertSame(2, Product::query()->count());
        $this->assertSame(2, ProductVariant::query()->whereNotNull('standalone_product_id')->count());
        $this->assertSame(2, ProductImage::query()->count());
        $this->assertEqualsCanonicalizing(
            [$legacy->slug.'-red', $legacy->slug.'-blue'],
            Product::query()->pluck('slug')->all(),
        );
        $this->assertDatabaseHas('product_groups', ['code' => 'legacy-'.$legacy->id]);
        $this->artisan('catalog:migrate-standalone-products', ['--apply' => true])->assertSuccessful();
        $this->assertSame(2, Product::query()->count());
        $this->artisan('catalog:migrate-standalone-products', ['--apply' => true, '--finalize' => true])->assertSuccessful();
        $this->assertSame(0, ProductVariant::query()->count());
        $this->artisan('catalog:migrate-standalone-products')->expectsOutputToContain('DRY-RUN: 0 legacy product cards')->assertSuccessful();
    }

    public function test_legacy_converter_cleans_cloned_files_when_database_conversion_rolls_back(): void
    {
        Storage::fake('public');
        $legacy = Product::factory()->create([
            'sku' => null, 'article_number' => null, 'barcode' => null, 'unit' => null,
            'price' => null, 'old_price' => null, 'stock_quantity' => null, 'is_active' => false,
        ]);
        $existingPath = "product-images/{$legacy->id}/existing.jpg";
        Storage::disk('public')->put($existingPath, 'image');
        foreach ([[$existingPath, true], ["product-images/{$legacy->id}/missing.jpg", false]] as [$path, $primary]) {
            ProductImage::query()->create([
                'product_id' => $legacy->id, 'disk' => 'public', 'path' => $path,
                'mime_type' => 'image/jpeg', 'size' => 5, 'is_primary' => $primary,
            ]);
        }
        ProductVariant::factory()->count(2)->create(['product_id' => $legacy->id, 'is_active' => false]);

        try {
            $this->artisan('catalog:migrate-standalone-products', ['--apply' => true])->run();
            $this->fail('Expected a missing source image to fail conversion.');
        } catch (\RuntimeException) {
            $this->assertSame(1, Product::query()->count());
            $this->assertSame(0, ProductVariant::query()->whereNotNull('standalone_product_id')->count());
            $this->assertSame([$existingPath], Storage::disk('public')->allFiles('product-images'));
        }
    }

    public function test_nested_variant_api_is_removed(): void
    {
        $this->actingAs($this->catalogManager())->getJson('/api/v1/admin/products/1/variants')->assertNotFound();
    }

    public function test_opt_in_legacy_table_cleanup_refuses_data_and_is_reversible(): void
    {
        $migration = require base_path('database/finalization-migrations/2026_08_21_230000_drop_legacy_product_variant_tables.php');
        $variant = ProductVariant::factory()->create();

        try {
            $migration->up();
            $this->fail('Expected cleanup to refuse a non-empty legacy table.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('1 product variants remain', $exception->getMessage());
        }

        $variant->delete();
        $migration->up();

        try {
            $this->assertFalse(Schema::hasTable('product_variants'));
            $this->assertFalse(Schema::hasTable('product_variant_attribute_values'));
            $this->artisan('catalog:migrate-standalone-products')
                ->expectsOutput('Legacy variant tables have already been removed.')
                ->assertSuccessful();
        } finally {
            $migration->down();
        }

        $this->assertTrue(Schema::hasTable('product_variants'));
        $this->assertTrue(Schema::hasTable('product_variant_attribute_values'));
    }

    private function catalogManager(): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'catalog-manager')->sole());

        return $user;
    }
}
