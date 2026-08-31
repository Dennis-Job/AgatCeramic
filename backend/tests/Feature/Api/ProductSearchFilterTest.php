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

class ProductSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_search_and_filter_products(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $tiles = Category::factory()->create();
        $mosaic = Category::factory()->create();
        $brand = Brand::factory()->create();
        $match = Product::factory()->create(['category_id' => $tiles->id, 'brand_id' => $brand->id, 'name' => 'Marble White', 'slug' => 'marble-white', 'is_active' => true, 'is_on_sale' => true]);
        $match->update(['sku' => 'MARBLE-6060', 'article_number' => 'Vendor-Art-6060', 'barcode' => '0123456789012', 'price' => 1990, 'stock_quantity' => 10]);
        $other = Product::factory()->create(['category_id' => $mosaic->id, 'brand_id' => null, 'name' => 'Blue Mosaic', 'slug' => 'blue-mosaic', 'is_active' => false]);
        $other->update(['sku' => 'MOSAIC-BLUE', 'price' => 990, 'stock_quantity' => 0]);

        $this->actingAs($actor)->getJson('/api/v1/admin/products?search=marble-6060')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->actingAs($actor)->getJson('/api/v1/admin/products?search=vendor-art')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->actingAs($actor)->getJson('/api/v1/admin/products?search=4567890')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->actingAs($actor)->getJson("/api/v1/admin/products?category_id={$tiles->id}&brand_id={$brand->id}&is_active=1&has_stock=1&price_from=1500&price_to=2000")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->actingAs($actor)->getJson('/api/v1/admin/products?has_stock=0&is_active=0')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
        $this->actingAs($actor)->getJson('/api/v1/admin/products?is_on_sale=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public function test_product_filters_validate_and_require_catalog_access(): void
    {
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/products?search=tile')->assertForbidden();
        $actor = $this->userWithRole('catalog-manager');

        $this->actingAs($actor)->getJson('/api/v1/admin/products?price_from=100&price_to=10&has_stock=invalid&is_on_sale=invalid')
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['price_to', 'has_stock', 'is_on_sale']]]);

        $this->actingAs($actor)->getJson('/api/v1/admin/products?sort=price&direction=sideways')
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['sort', 'direction']]]);
    }

    public function test_products_default_to_newest_first_and_support_table_sorting(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $category = Category::factory()->create();
        $alpha = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Альфа',
            'sku' => '03000003',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subHour(),
        ]);
        $beta = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Бета',
            'sku' => '01000001',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDays(3),
        ]);
        $gamma = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Гамма',
            'sku' => '02000002',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->actingAs($actor)->getJson('/api/v1/admin/products')
            ->assertOk()->assertJsonPath('data.*.id', [$beta->id, $gamma->id, $alpha->id]);
        $this->actingAs($actor)->getJson('/api/v1/admin/products?sort=sku&direction=asc')
            ->assertOk()->assertJsonPath('data.*.id', [$beta->id, $gamma->id, $alpha->id]);
        $this->actingAs($actor)->getJson('/api/v1/admin/products?sort=name&direction=desc')
            ->assertOk()->assertJsonPath('data.*.id', [$gamma->id, $beta->id, $alpha->id]);
        $this->actingAs($actor)->getJson('/api/v1/admin/products?sort=created_at&direction=asc')
            ->assertOk()->assertJsonPath('data.*.id', [$alpha->id, $gamma->id, $beta->id]);
        $this->actingAs($actor)->getJson('/api/v1/admin/products?sort=updated_at&direction=desc')
            ->assertOk()->assertJsonPath('data.*.id', [$alpha->id, $gamma->id, $beta->id]);
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
