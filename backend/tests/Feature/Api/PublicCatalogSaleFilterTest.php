<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCatalogSaleFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_filter_combines_with_stock_category_and_public_visibility(): void
    {
        $category = Category::factory()->create();
        $sale = Product::factory()->create(['category_id' => $category->id, 'is_on_sale' => true, 'stock_quantity' => 1, 'old_price' => null]);
        $unavailableSale = Product::factory()->create(['category_id' => $category->id, 'is_on_sale' => true, 'stock_quantity' => 0]);
        Product::factory()->create(['category_id' => $category->id, 'is_on_sale' => false, 'stock_quantity' => 1, 'price' => '100', 'old_price' => '200']);
        Product::factory()->create(['category_id' => $category->id, 'is_on_sale' => true, 'is_active' => false, 'stock_quantity' => 1]);
        Product::factory()->create(['is_on_sale' => true, 'stock_quantity' => 1]);
        $hiddenCategory = Category::factory()->create(['is_active' => false]);
        Product::factory()->create(['category_id' => $hiddenCategory->id, 'is_on_sale' => true, 'stock_quantity' => 1]);

        $response = $this->getJson('/api/v1/catalog?is_on_sale=1&category='.$category->slug)
            ->assertOk()->assertJsonPath('meta.total', 2);
        self::assertEqualsCanonicalizing([$sale->id, $unavailableSale->id], array_column($response->json('data'), 'id'));

        $this->getJson('/api/v1/catalog?is_on_sale=1&in_stock=1&category='.$category->slug)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sale->id);

        $this->getJson('/api/v1/catalog?is_on_sale=1')->assertOk()->assertJsonPath('meta.total', 3);
        foreach (['', '?is_on_sale=0'] as $query) {
            $this->getJson('/api/v1/catalog'.$query)->assertOk()->assertJsonPath('meta.total', 4);
        }
    }

    public function test_sale_filter_applies_before_pagination_and_preserves_navigation_and_facets(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create(['show_in_catalog_menu' => true]);
        $otherBrand = Brand::factory()->create(['show_in_catalog_menu' => true]);
        $attribute = Attribute::factory()->create(['type' => 'select', 'is_filterable' => true]);
        $category->attributes()->attach($attribute);
        $attribute->options()->createMany([['value' => 'sale', 'label' => 'Sale'], ['value' => 'regular', 'label' => 'Regular']]);
        $regular = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $otherBrand->id, 'name' => 'A regular product', 'is_on_sale' => false]);
        ProductAttributeValue::query()->create(['product_id' => $regular->id, 'attribute_id' => $attribute->id, 'value' => 'regular']);
        foreach (range(1, 25) as $i) {
            $sale = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => sprintf('Sale %02d', $i), 'is_on_sale' => true]);
        }
        ProductAttributeValue::query()->create(['product_id' => $sale->id, 'attribute_id' => $attribute->id, 'value' => 'sale']);

        $response = $this->getJson('/api/v1/catalog?'.http_build_query(['category' => $category->slug, 'is_on_sale' => 1, 'include_filters' => 1, 'page' => 2]))
            ->assertOk()->assertJsonPath('meta.total', 25)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sale->id)
            ->assertJsonCount(2, 'filters.brands')->assertJsonCount(2, 'brand_navigation')
            ->assertJsonPath('category_navigation.total', 26)->assertJsonPath('category_navigation.categories.0.product_count', 26);
        self::assertEqualsCanonicalizing(['sale', 'regular'], array_column($response->json('filters.attributes.0.options'), 'value'));
    }

    #[DataProvider('malformedSaleFilters')]
    public function test_rejects_malformed_sale_filter(string $query): void
    {
        $this->getJson('/api/v1/catalog?'.$query)->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['is_on_sale']]]);
    }

    public static function malformedSaleFilters(): array
    {
        return [
            'text' => ['is_on_sale=yes'],
            'out of range' => ['is_on_sale=2'],
            'empty' => ['is_on_sale='],
            'array' => ['is_on_sale[]=1'],
            'object' => ['is_on_sale[enabled]=1'],
        ];
    }
}
