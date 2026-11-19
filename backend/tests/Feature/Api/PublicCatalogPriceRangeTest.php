<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogPriceRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_bounds_cover_public_scope_before_extra_filters_and_pagination(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $scope = ['category_id' => $category->id, 'brand_id' => $brand->id];
        Product::factory()->count(25)->create([...$scope, 'price' => '150.50']);
        Product::factory()->create([...$scope, 'price' => '9999999999.99', 'stock_quantity' => 0, 'is_on_sale' => false]);
        Product::factory()->create([...$scope, 'price' => '0.00']);
        Product::factory()->create([...$scope, 'price' => null]);
        Product::factory()->create([...$scope, 'price' => '0.01', 'is_active' => false]);
        Product::factory()->create(['category_id' => $category->id, 'price' => '0.00']);
        $response = $this->getJson('/api/v1/catalog?'.http_build_query([
            'category' => $category->slug, 'brand' => $brand->slug, 'include_filters' => 1,
            'page' => 2, 'price_from' => '150.50', 'price_to' => '150.50', 'in_stock' => 1, 'is_on_sale' => 1,
        ]))->assertOk()->assertJsonPath('filters.price_range', ['min' => '0.00', 'max' => '9999999999.99']);
        $response->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonMissingPath('filters');
    }

    public function test_bounds_exclude_other_brands_and_hidden_categories_and_include_descendants_when_requested(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $hidden = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => false]);
        $brand = Brand::factory()->create();
        Product::factory()->create(['category_id' => $parent->id, 'brand_id' => $brand->id, 'price' => '0.01']);
        Product::factory()->create(['category_id' => $child->id, 'brand_id' => $brand->id, 'price' => '300.25']);
        Product::factory()->create(['category_id' => $hidden->id, 'brand_id' => $brand->id, 'price' => '500.00']);
        Product::factory()->create(['category_id' => $child->id, 'price' => '900.00']);
        $url = '/api/v1/catalog?'.http_build_query(['category' => $parent->slug, 'brand' => $brand->slug, 'include_filters' => 1]);
        $this->getJson($url)->assertOk()->assertJsonPath('filters.price_range', ['min' => '0.01', 'max' => '0.01']);
        $this->getJson($url.'&category_descendants=1')->assertOk()->assertJsonPath('filters.price_range', ['min' => '0.01', 'max' => '300.25']);
    }

    public function test_empty_or_unpriced_scopes_have_no_range(): void
    {
        $category = Category::factory()->create();
        $url = '/api/v1/catalog?'.http_build_query(['category' => $category->slug, 'include_filters' => 1]);
        $this->getJson($url)->assertOk()->assertJsonStructure(['filters' => ['price_range']])->assertJsonPath('filters.price_range', null);
        Product::factory()->create(['category_id' => $category->id, 'price' => null]);
        $this->getJson($url)->assertOk()->assertJsonPath('filters.price_range', null);
    }
}
