<?php

namespace Tests\Feature\Api;

use App\Jobs\DeleteStoredFile;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use App\Models\Role;
use App\Models\StorageCleanupTask;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            'sku' => 'MARBLE-WHITE-6060',
            'unit' => 'square_meter',
            'price' => '1990.00',
            'stock_quantity' => 12,
            'is_active' => true,
            'is_on_sale' => true,
        ])->assertCreated()
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.brand.id', $brand->id)
            ->assertJsonPath('data.is_on_sale', true);
        $id = $created->json('data.id');

        $this->actingAs($actor)->getJson('/api/v1/admin/products')
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.per_page', 25);
        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$id}", [
            'brand_id' => null, 'is_active' => false, 'is_on_sale' => false,
        ])->assertOk()
            ->assertJsonPath('data.brand_id', null)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.is_on_sale', false);
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

    public function test_product_cannot_move_when_its_product_or_variant_values_are_not_assigned_to_the_target_category(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $source = Category::factory()->create();
        $target = Category::factory()->create();
        $productAttribute = Attribute::factory()->create();
        $variantAttribute = Attribute::factory()->create();
        $source->attributes()->attach([$productAttribute->id, $variantAttribute->id]);
        $product = Product::factory()->create(['category_id' => $source->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $productAttribute->id, 'value' => 'porcelain']);
        ProductVariantAttributeValue::query()->create(['product_variant_id' => $variant->id, 'attribute_id' => $variantAttribute->id, 'value' => '60x60']);

        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$product->id}", [
            'category_id' => $target->id,
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['category_id']]]);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'category_id' => $source->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'product.updated', 'entity_id' => $product->id]);
    }

    public function test_product_category_move_enforces_required_attributes_and_accepts_a_compatible_value_set(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $source = Category::factory()->create();
        $target = Category::factory()->create();
        $required = Attribute::factory()->create();
        $source->attributes()->attach($required->id, ['is_required' => true]);
        $target->attributes()->attach($required->id, ['is_required' => true]);
        $product = Product::factory()->create(['category_id' => $source->id]);

        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$product->id}", [
            'category_id' => $target->id,
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['category_id']]]);

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}/attributes", [
            'attributes' => [['attribute_id' => $required->id, 'value' => 'present']],
        ])->assertOk();

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}", [
            'category_id' => $target->id,
        ])->assertOk()->assertJsonPath('data.category_id', $target->id);
    }

    public function test_deleting_a_product_removes_all_its_image_files(): void
    {
        Storage::fake('public');
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();
        $paths = ["product-images/{$product->id}/main.jpg", "product-images/{$product->id}/detail.webp"];

        foreach ($paths as $index => $path) {
            Storage::disk('public')->put($path, 'image content');
            ProductImage::query()->create([
                'product_id' => $product->id,
                'disk' => 'public',
                'path' => $path,
                'mime_type' => $index === 0 ? 'image/jpeg' : 'image/webp',
                'size' => 13,
                'is_primary' => $index === 0,
            ]);
        }

        $this->actingAs($actor)->deleteJson("/api/v1/admin/products/{$product->id}")->assertNoContent();

        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        StorageCleanupTask::query()->whereIn('path', $paths)->each(
            fn (StorageCleanupTask $task) => (new DeleteStoredFile($task->id))->handle()
        );
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_deleting_a_product_removes_image_files_from_every_configured_disk(): void
    {
        Storage::fake('public');
        Storage::fake('archive');
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();
        $images = [
            ['disk' => 'public', 'path' => "product-images/{$product->id}/main.jpg", 'mime_type' => 'image/jpeg'],
            ['disk' => 'archive', 'path' => "product-images/{$product->id}/detail.webp", 'mime_type' => 'image/webp'],
        ];

        foreach ($images as $index => $image) {
            Storage::disk($image['disk'])->put($image['path'], 'image content');
            ProductImage::query()->create([
                'product_id' => $product->id,
                ...$image,
                'size' => 13,
                'is_primary' => $index === 0,
            ]);
        }

        $this->actingAs($actor)->deleteJson("/api/v1/admin/products/{$product->id}")->assertNoContent();

        StorageCleanupTask::query()->whereIn('path', collect($images)->pluck('path'))->each(
            fn (StorageCleanupTask $task) => (new DeleteStoredFile($task->id))->handle()
        );
        foreach ($images as $image) {
            Storage::disk($image['disk'])->assertMissing($image['path']);
        }
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
