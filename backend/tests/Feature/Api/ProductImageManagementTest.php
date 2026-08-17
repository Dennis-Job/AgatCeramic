<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_upload_manage_and_delete_product_images(): void
    {
        Storage::fake('public');
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();

        $first = $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('tile.jpg', 800, 600), 'alt' => 'White tile', 'sort_order' => 20,
        ])->assertCreated()->assertJsonPath('data.is_primary', true)->assertJsonPath('data.alt', 'White tile');
        $firstId = $first->json('data.id');
        $firstPath = ProductImage::query()->findOrFail($firstId)->path;
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('tile-detail.png'), 'is_primary' => true,
        ])->assertCreated()->assertJsonPath('data.is_primary', true);
        $secondId = $second->json('data.id');
        $this->assertDatabaseHas('product_images', ['id' => $firstId, 'is_primary' => false]);

        $this->actingAs($actor)->post("/api/v1/admin/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('tile-side.webp'), 'is_primary' => '0',
        ])->assertCreated()->assertJsonPath('data.is_primary', false);

        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$product->id}/images/{$firstId}", [
            'is_primary' => true, 'alt' => 'Main image',
        ])->assertOk()->assertJsonPath('data.is_primary', true)->assertJsonPath('data.alt', 'Main image');
        $this->actingAs($actor)->getJson("/api/v1/admin/products/{$product->id}/images")
            ->assertOk()->assertJsonPath('data.0.id', $firstId)->assertJsonPath('meta.per_page', 100);
        $this->actingAs($actor)->deleteJson("/api/v1/admin/products/{$product->id}/images/{$firstId}")->assertNoContent();

        $this->assertDatabaseMissing('product_images', ['id' => $firstId]);
        Storage::disk('public')->assertMissing($firstPath);
        $this->assertDatabaseHas('product_images', ['id' => $secondId, 'is_primary' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.image-uploaded', 'entity_id' => $firstId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.image-updated', 'entity_id' => $firstId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.image-deleted', 'entity_id' => $firstId]);
    }

    public function test_product_image_upload_validates_access_and_parent_scope(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();
        $this->actingAs($this->userWithRole('analyst'))->getJson("/api/v1/admin/products/{$product->id}/images")->assertForbidden();
        $actor = $this->userWithRole('catalog-manager');

        $this->actingAs($actor)->postJson("/api/v1/admin/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->create('document.pdf', 12, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['image']]]);

        $other = Product::factory()->create();
        $image = ProductImage::query()->create(['product_id' => $other->id, 'path' => 'product-images/test.jpg', 'mime_type' => 'image/jpeg', 'size' => 1]);
        $this->actingAs($actor)->patchJson("/api/v1/admin/products/{$product->id}/images/{$image->id}", ['alt' => 'No access'])->assertNotFound();
    }

    public function test_database_allows_only_one_primary_image_per_product(): void
    {
        $product = Product::factory()->create();
        ProductImage::query()->create(['product_id' => $product->id, 'path' => 'product-images/primary.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'is_primary' => true]);

        $this->expectException(QueryException::class);

        ProductImage::query()->create(['product_id' => $product->id, 'path' => 'product-images/another-primary.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'is_primary' => true]);
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
