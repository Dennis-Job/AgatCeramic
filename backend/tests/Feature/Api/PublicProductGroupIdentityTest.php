<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductGroupIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_variants_expose_distinct_sku_for_identical_names_and_axes(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $color = Attribute::factory()->create(['type' => 'string', 'is_visible_on_product_page' => true]);
        $category->attributes()->attach($color->id);
        $products = Product::factory()->count(2)->create([
            'name' => 'Аллея', 'category_id' => $category->id, 'brand_id' => null,
            'is_active' => true, 'is_on_sale' => true,
        ]);
        $group = ProductGroup::factory()->create();
        $group->axes()->attach($color->id);
        $group->products()->attach($products->modelKeys());
        foreach ($products as $product) {
            $product->attributeValues()->create(['attribute_id' => $color->id, 'value' => 'Серый']);
        }

        $this->getJson('/api/v1/products/'.$products[0]->slug)->assertOk()
            ->assertJsonPath('data.group.products.0.sku', $products[0]->sku)
            ->assertJsonPath('data.group.products.1.sku', $products[1]->sku)
            ->assertJsonCount(1, 'data.group.axes')
            ->assertJsonMissingPath('data.group.products.0.stock_quantity');
    }
}
