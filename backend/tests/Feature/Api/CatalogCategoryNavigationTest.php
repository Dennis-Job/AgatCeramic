<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCategoryNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_categories_include_descendant_counts_and_remain_stable_while_refining_results(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kerama Marazzi', 'slug' => 'kerama-marazzi']);
        $other = Brand::factory()->create();
        $parent = Category::factory()->create(['name' => 'Ceramics', 'sort_order' => 1]);
        $tile = Category::factory()->create(['parent_id' => $parent->id, 'name' => 'Tile', 'sort_order' => 2]);
        $steps = Category::factory()->create(['name' => 'Steps', 'sort_order' => 3]);
        $unrelated = Category::factory()->create(['name' => 'Glue', 'sort_order' => 4]);
        Product::factory()->count(2)->create(['brand_id' => $brand->id, 'category_id' => $tile->id, 'stock_quantity' => 0]);
        Product::factory()->create(['brand_id' => $brand->id, 'category_id' => $steps->id]);
        Product::factory()->create(['brand_id' => $other->id, 'category_id' => $unrelated->id]);
        Product::factory()->create(['brand_id' => $brand->id, 'category_id' => $tile->id, 'is_active' => false]);
        $inactive = Category::factory()->create(['is_active' => false]);
        Product::factory()->create(['brand_id' => $brand->id, 'category_id' => $inactive->id]);
        $expected = [
            'brand' => ['id' => $brand->id, 'name' => $brand->name, 'slug' => $brand->slug],
            'total' => 3,
            'categories' => [
                $this->entry($parent, 2), $this->entry($tile, 2), $this->entry($steps, 1),
            ],
        ];
        foreach ([
            ['brand' => $brand->slug],
            ['brand' => $brand->slug, 'category' => $tile->slug, 'category_descendants' => 1, 'include_filters' => 1],
            ['brand' => $brand->slug, 'category' => $steps->slug, 'price_from' => '999999', 'in_stock' => 1, 'page' => 2, 'sort' => 'price_desc', 'collection' => 'Missing', 'attributes' => '{}'],
        ] as $filters) {
            $response = $this->getJson('/api/v1/catalog?'.http_build_query($filters))->assertOk();
            $response->assertJsonPath('category_navigation', $expected);
            $response->assertJsonFragment(['slug' => $unrelated->slug]);
        }
    }

    public function test_global_categories_keep_empty_categories_and_total_does_not_sum_ancestors_twice(): void
    {
        $parent = Category::factory()->create(['sort_order' => 1]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'sort_order' => 2]);
        $empty = Category::factory()->create(['sort_order' => 3]);
        Product::factory()->create(['category_id' => $parent->id]);
        Product::factory()->count(2)->create(['category_id' => $child->id]);
        $this->getJson('/api/v1/catalog?category='.$empty->slug.'&price_from=999999')->assertOk()
            ->assertJsonPath('category_navigation', [
                'brand' => null,
                'total' => 3,
                'categories' => [$this->entry($parent, 3), $this->entry($child, 2), $this->entry($empty, 0)],
            ]);
    }

    public function test_empty_selected_brand_still_has_its_name_even_when_not_published_in_brand_menu(): void
    {
        $brand = Brand::factory()->create(['show_in_catalog_menu' => false]);
        Category::factory()->create();
        $this->getJson('/api/v1/catalog?brand='.$brand->slug)->assertOk()
            ->assertJsonPath('category_navigation', [
                'brand' => ['id' => $brand->id, 'name' => $brand->name, 'slug' => $brand->slug],
                'total' => 0, 'categories' => [],
            ])->assertJsonPath('brand_navigation', []);
    }

    public function test_unknown_brand_has_no_categories_or_global_total(): void
    {
        Product::factory()->create();
        $this->getJson('/api/v1/catalog?brand=missing')->assertOk()
            ->assertJsonPath('category_navigation', ['brand' => null, 'total' => 0, 'categories' => []]);
    }

    public function test_active_ancestor_counts_traverse_unpublished_intermediate_categories(): void
    {
        $brand = Brand::factory()->create();
        $parent = Category::factory()->create(['sort_order' => 1]);
        $inactive = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => false]);
        $child = Category::factory()->create(['parent_id' => $inactive->id, 'sort_order' => 2]);
        Product::factory()->create(['brand_id' => $brand->id, 'category_id' => $child->id]);
        $this->getJson('/api/v1/catalog?brand='.$brand->slug)->assertOk()
            ->assertJsonPath('category_navigation.categories', [$this->entry($parent, 1), $this->entry($child, 1)]);
    }

    /** @return array{id: int, name: string, slug: string, parent_id: int|null, product_count: int} */
    private function entry(Category $category, int $count): array
    {
        return ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'parent_id' => $category->parent_id, 'product_count' => $count];
    }
}
