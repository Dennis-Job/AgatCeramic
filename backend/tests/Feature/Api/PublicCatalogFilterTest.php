<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_combines_filters_before_pagination_and_keeps_metadata_before_filters(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $collection = ProductCollection::factory()->create(['brand_id' => $brand->id]);
        $attribute = $this->attribute($category, 'select');
        $attribute->options()->createMany([
            ['value' => 'red', 'label' => 'Красный'], ['value' => 'blue', 'label' => 'Синий'], ['value' => 'unused', 'label' => 'Не используется'],
        ]);
        $match = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'collection_id' => $collection->id, 'price' => '150.50', 'stock_quantity' => 1]);
        $this->value($match, $attribute, 'red');
        $other = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'price' => '500', 'stock_quantity' => 0]);
        $this->value($other, $attribute, 'blue');
        $hidden = Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);
        $this->value($hidden, $attribute, 'unused');
        $response = $this->catalog([
            'category' => $category->slug, 'brand' => $brand->slug, 'collection' => $collection->name,
            'price_from' => '150.50', 'price_to' => '150.50', 'in_stock' => 1, 'include_filters' => 1,
            'attributes' => json_encode([$attribute->id => ['values' => ['red']]]),
        ])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('meta.total', 1)->assertJsonPath('filters.brands.0.id', $brand->id)
            ->assertJsonPath('filters.collections.0.id', $collection->id)
            ->assertJsonPath('filters.collections.0.brand_slug', $brand->slug);
        $this->assertSame([
            ['value' => 'red', 'label' => 'Красный'], ['value' => 'blue', 'label' => 'Синий'],
        ], $response->json('filters.attributes.0.options'));
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonMissingPath('filters')->assertJsonMissingPath('data.0.stock_quantity');
    }

    #[DataProvider('attributeCases')]
    public function test_filters_all_attribute_types_without_losing_false_zero_or_array_values(string $type, mixed $value, mixed $otherValue, array $filter): void
    {
        $category = Category::factory()->create();
        $attribute = $this->attribute($category, $type);
        $match = Product::factory()->create(['category_id' => $category->id]);
        $other = Product::factory()->create(['category_id' => $category->id]);
        $missing = Product::factory()->create(['category_id' => $category->id]);
        $this->value($match, $attribute, $value);
        $this->value($other, $attribute, $otherValue);
        $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$attribute->id => $filter])])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public static function attributeCases(): array
    {
        return [
            'select zero string' => ['select', '0', 0, ['values' => ['0', 'red']]],
            'multiselect OR' => ['multiselect', ['red', 'blue'], ['green'], ['values' => ['blue', 'yellow']]],
            'boolean false' => ['boolean', false, 'false', ['values' => ['false']]],
            'integer zero' => ['integer', 0, 1, ['min' => '0', 'max' => '0']],
            'decimal' => ['decimal', '12.50', '12.51', ['min' => '12.5', 'max' => '12.50']],
            'date inclusive' => ['date', '2026-11-11', '2026-11-12', ['min' => '2026-11-11', 'max' => '2026-11-11']],
            'string literal substring' => ['string', 'Матовый 100%_a', 'Матовый 100xxa', ['text' => '100%_a']],
            'text case insensitive' => ['text', 'Ceramic glazed', 'Stone', ['text' => 'GLAZED']],
        ];
    }

    public function test_postgresql_text_filters_match_cyrillic_without_case_sensitivity(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL Unicode LOWER regression; SQLite LOWER is ASCII-only.');
        }
        $category = Category::factory()->create();
        foreach (['string', 'text'] as $type) {
            $attribute = $this->attribute($category, $type);
            $match = Product::factory()->create(['category_id' => $category->id]);
            $other = Product::factory()->create(['category_id' => $category->id]);
            $this->value($match, $attribute, 'Белый МАТОВЫЙ керамогранит');
            $this->value($other, $attribute, 'Белый глянцевый керамогранит');
            $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$attribute->id => ['text' => 'матовый']])])
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        }
    }

    public function test_attributes_require_filterable_explicit_selected_category_assignment(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $parentAttribute = $this->attribute($parent, 'boolean');
        $childAttribute = $this->attribute($child, 'boolean');
        $disabled = $this->attribute($parent, 'boolean');
        $disabled->update(['is_filterable' => false]);
        $legacy = $this->attribute($parent, 'select');
        $legacy->update(['slug' => 'kollektsiya']);
        Product::factory()->create(['category_id' => $child->id]);
        $this->catalog(['include_filters' => 1])->assertOk()->assertJsonPath('filters.attributes', []);
        $this->catalog(['category' => $parent->slug, 'category_descendants' => 1, 'include_filters' => 1])
            ->assertOk()->assertJsonCount(1, 'filters.attributes')->assertJsonPath('filters.attributes.0.id', $parentAttribute->id);
        foreach ([[$child->slug, $parentAttribute], [$parent->slug, $childAttribute], [$parent->slug, $disabled], [$parent->slug, $legacy], [null, $parentAttribute]] as [$slug, $attribute]) {
            $this->catalog(['category' => $slug, 'attributes' => json_encode([$attribute->id => ['values' => ['true']]])])->assertUnprocessable();
        }
    }

    public function test_descendants_are_opt_in_and_active_children_of_inactive_ancestors_remain_public(): void
    {
        $parent = Category::factory()->create(['sort_order' => 0]);
        $child = Category::factory()->create(['parent_id' => $parent->id, 'sort_order' => 1]);
        $hidden = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => false]);
        $orphan = Category::factory()->create(['parent_id' => $hidden->id]);
        $direct = Product::factory()->create(['category_id' => $parent->id]);
        Product::factory()->create(['category_id' => $child->id]);
        Product::factory()->create(['category_id' => $hidden->id]);
        Product::factory()->create(['category_id' => $orphan->id]);
        $this->catalog(['category' => $parent->slug])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $direct->id);
        $response = $this->catalog(['category' => $parent->slug, 'category_descendants' => 1])->assertOk()->assertJsonCount(3, 'data');
        $categories = collect($response->json('categories'))->keyBy('id');
        $this->assertSame($parent->id, $categories[$child->id]['parent_id']);
        $this->assertSame($hidden->id, $categories[$orphan->id]['parent_id']);
        $this->assertFalse($categories->has($hidden->id));
        $this->catalog(['category' => $orphan->slug])->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_price_order_is_stable_null_last_and_bounds_exclude_unpriced_products(): void
    {
        $category = Category::factory()->create();
        $null = Product::factory()->create(['category_id' => $category->id, 'price' => null, 'name' => 'A']);
        $low = Product::factory()->create(['category_id' => $category->id, 'price' => '10', 'name' => 'B', 'created_at' => '2026-01-01']);
        $high = Product::factory()->create(['category_id' => $category->id, 'price' => '20', 'name' => 'C', 'created_at' => '2026-02-01']);
        $tie = Product::factory()->create(['category_id' => $category->id, 'price' => '20', 'name' => 'D', 'created_at' => '2026-02-01']);
        foreach ([['price_asc', [$low->id, $high->id, $tie->id, $null->id]], ['price_desc', [$high->id, $tie->id, $low->id, $null->id]], ['name_desc', [$tie->id, $high->id, $low->id, $null->id]]] as [$sort, $ids]) {
            $this->assertSame($ids, array_column($this->catalog(['category' => $category->slug, 'sort' => $sort])->assertOk()->json('data'), 'id'));
        }
        $this->catalog(['category' => $category->slug, 'price_from' => '0', 'price_to' => '10'])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $low->id);
    }

    public function test_metadata_sees_values_beyond_first_page_and_never_other_categories_or_brands(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $otherBrand = Brand::factory()->create();
        $attribute = $this->attribute($category, 'multiselect');
        $attribute->options()->createMany([['value' => 'first', 'label' => 'Первый'], ['value' => 'last', 'label' => 'Последний'], ['value' => 'foreign', 'label' => 'Чужой']]);
        foreach (range(1, 25) as $i) {
            $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => sprintf('Item %02d', $i)]);
            $this->value($product, $attribute, [$i === 25 ? 'last' : 'first']);
        }
        $foreign = Product::factory()->create(['brand_id' => $otherBrand->id]);
        $this->value($foreign, $attribute, ['foreign']);
        $response = $this->catalog(['category' => $category->slug, 'include_filters' => 1])->assertOk()->assertJsonCount(24, 'data')->assertJsonPath('meta.total', 25)->assertJsonCount(1, 'filters.brands');
        $this->assertSame(['first', 'last'], array_column($response->json('filters.attributes.0.options'), 'value'));
        $this->catalog(['category' => $category->slug, 'page' => 2])->assertOk()->assertJsonCount(1, 'data');
    }

    #[DataProvider('invalidQueries')]
    public function test_rejects_malformed_or_unbounded_filters(array $query): void
    {
        $this->catalog($query)->assertUnprocessable();
    }

    public static function invalidQueries(): array
    {
        return array_map(fn ($query) => [$query], [
            ['attributes' => '{'], ['attributes' => '[]'], ['attributes' => 'false'], ['attributes' => '{"1":{}}'],
            ['attributes' => str_repeat('x', 16385)], ['attributes' => ['1' => ['values' => ['x']]]],
            ['price_from' => '-1'], ['price_from' => '1e5'], ['price_from' => '20', 'price_to' => '10'],
            ['sort' => 'random'], ['in_stock' => 'yes'], ['category_descendants' => 'yes'], ['include_filters' => 'yes'],
            ['collection' => 'Example'],
        ]);
    }

    public function test_rejects_type_inappropriate_attribute_shapes_bounds_and_excess_values(): void
    {
        $category = Category::factory()->create();
        foreach ([['boolean', ['values' => ['0']]], ['select', ['values' => [false]]], ['select', ['values' => []]], ['select', ['values' => array_fill(0, 51, 'x')]], ['integer', ['min' => '0.5']], ['decimal', ['min' => '2', 'max' => '1']], ['date', ['min' => '2026-02-30']], ['date', ['min' => '2026-02-01', 'max' => '2026-01-01']], ['text', ['text' => '']], ['string', ['values' => ['x']]], ['select', ['values' => ['x'], 'text' => 'x']]] as [$type, $filter]) {
            $attribute = $this->attribute($category, $type);
            $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$attribute->id => $filter])])->assertUnprocessable();
        }
    }

    public function test_empty_present_range_bounds_return_validation_errors_instead_of_server_errors(): void
    {
        $category = Category::factory()->create();
        foreach (['integer', 'decimal', 'date'] as $type) {
            $attribute = $this->attribute($category, $type);
            $valid = $type === 'date' ? '2026-11-11' : '10';
            foreach ([['min' => '', 'max' => $valid], ['min' => $valid, 'max' => '']] as $filter) {
                $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$attribute->id => $filter])])->assertUnprocessable();
            }
        }
    }

    public function test_metadata_batches_distinct_values_instead_of_querying_each_attribute(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $attribute = $this->attribute($category, 'select');
        $this->value($product, $attribute, 'red');
        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            $this->catalog(['category' => $category->slug, 'include_filters' => 1])->assertOk();
            $one = count(array_filter(DB::getQueryLog(), fn ($query) => str_contains(strtolower($query['query']), 'select distinct') && str_contains($query['query'], 'product_attribute_values')));
            foreach (range(1, 9) as $index) {
                $attribute = $this->attribute($category, 'select');
                $this->value($product, $attribute, 'red');
            }
            DB::flushQueryLog();
            $this->catalog(['category' => $category->slug, 'include_filters' => 1])->assertOk()->assertJsonCount(10, 'filters.attributes');
            $ten = count(array_filter(DB::getQueryLog(), fn ($query) => str_contains(strtolower($query['query']), 'select distinct') && str_contains($query['query'], 'product_attribute_values')));
            $this->assertSame(1, $one);
            $this->assertSame($one, $ten);
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_characteristics_combine_with_and_and_boolean_metadata_preserves_both_values(): void
    {
        $category = Category::factory()->create();
        $boolean = $this->attribute($category, 'boolean');
        $number = $this->attribute($category, 'integer');
        $old = Product::factory()->create(['category_id' => $category->id, 'created_at' => '2026-01-01']);
        $new = Product::factory()->create(['category_id' => $category->id, 'created_at' => '2026-02-01']);
        $other = Product::factory()->create(['category_id' => $category->id, 'created_at' => '2026-03-01']);
        foreach ([[$old, false, 0], [$new, false, 1], [$other, true, 0]] as [$product, $bool, $num]) {
            $this->value($product, $boolean, $bool);
            $this->value($product, $number, $num);
        }
        $response = $this->catalog(['category' => $category->slug, 'include_filters' => 1, 'sort' => 'newest', 'attributes' => json_encode([
            $boolean->id => ['values' => ['false']], $number->id => ['min' => '0', 'max' => '0'],
        ])])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old->id);
        $metadata = collect($response->json('filters.attributes'))->keyBy('id');
        $this->assertSame([['value' => 'true', 'label' => 'Да'], ['value' => 'false', 'label' => 'Нет']], $metadata[$boolean->id]['options']);
        $this->catalog(['category' => $category->slug, 'sort' => 'newest'])->assertOk()->assertJsonPath('data.0.id', $other->id);
        $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$boolean->id => ['values' => ['false']], $number->id => ['min' => '999']])])->assertOk()->assertJsonCount(0, 'data');
    }

    private function attribute(Category $category, string $type): Attribute
    {
        $attribute = Attribute::factory()->create(['type' => $type, 'is_filterable' => true]);
        $category->attributes()->attach($attribute);

        return $attribute;
    }

    private function value(Product $product, Attribute $attribute, mixed $value): void
    {
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => $value]);
    }

    private function catalog(array $query): TestResponse
    {
        return $this->getJson('/api/v1/catalog?'.http_build_query($query));
    }
}
