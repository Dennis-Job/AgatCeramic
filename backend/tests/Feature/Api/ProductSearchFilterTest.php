<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
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
        $match = Product::factory()->create(['category_id' => $tiles->id, 'brand_id' => $brand->id, 'name' => 'Marble White', 'slug' => 'marble-white', 'is_active' => true]);
        ProductVariant::factory()->create(['product_id' => $match->id, 'sku' => 'MARBLE-6060', 'price' => 1990, 'stock_quantity' => 10]);
        $other = Product::factory()->create(['category_id' => $mosaic->id, 'brand_id' => null, 'name' => 'Blue Mosaic', 'slug' => 'blue-mosaic', 'is_active' => false]);
        ProductVariant::factory()->create(['product_id' => $other->id, 'sku' => 'MOSAIC-BLUE', 'price' => 990, 'stock_quantity' => 0]);

        $this->actingAs($actor)->getJson('/api/v1/admin/products?search=marble-6060')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->actingAs($actor)->getJson("/api/v1/admin/products?category_id={$tiles->id}&brand_id={$brand->id}&is_active=1&has_stock=1&price_from=1500&price_to=2000")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->actingAs($actor)->getJson('/api/v1/admin/products?has_stock=0&is_active=0')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
    }

    public function test_product_filters_validate_and_require_catalog_access(): void
    {
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/products?search=tile')->assertForbidden();
        $actor = $this->userWithRole('catalog-manager');

        $this->actingAs($actor)->getJson('/api/v1/admin/products?price_from=100&price_to=10&has_stock=invalid')
            ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['price_to', 'has_stock']]]);
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
