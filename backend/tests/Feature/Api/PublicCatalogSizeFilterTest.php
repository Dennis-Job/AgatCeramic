<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCatalogSizeFilterTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('sizeTypes')]
    public function test_size_options_use_distinct_actual_strings_from_entire_public_category_scope(string $type): void
    {
        $category = Category::factory()->create();
        $size = $this->sizeAttribute($category, $type);
        $brand = Brand::factory()->create();
        foreach (range(1, 25) as $i) {
            $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => sprintf('Item %02d', $i)]);
            $this->value($product, $size, $i === 25 ? ' 30 x 60 ' : '60x60');
        }
        foreach (['', '   ', 60, true, null, ['60x60'], ['size' => '60x60']] as $value) {
            $this->value(Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand->id]), $size, $value);
        }
        $this->value(Product::factory()->create(['category_id' => $category->id, 'is_active' => false]), $size, 'hidden');
        $this->value(Product::factory()->create(), $size, 'foreign');
        $hiddenCategory = Category::factory()->create(['parent_id' => $category->id, 'is_active' => false]);
        $this->value(Product::factory()->create(['category_id' => $hiddenCategory->id]), $size, 'hidden category');
        $size->options()->create(['value' => 'stale', 'label' => 'Unused historical option']);

        $this->catalog(['category' => $category->slug, 'category_descendants' => 1, 'include_filters' => 1, 'page' => 1])
            ->assertOk()->assertJsonCount(24, 'data')->assertJsonPath('filters.attributes.0.options', [
                ['value' => ' 30 x 60 ', 'label' => ' 30 x 60 '], ['value' => '60x60', 'label' => '60x60'],
            ]);
        $this->catalog(['category' => $hiddenCategory->slug, 'include_filters' => 1])->assertOk()->assertJsonPath('filters.attributes', []);
        $this->catalog(['include_filters' => 1])->assertOk()->assertJsonPath('filters.attributes', []);
        $emptyCategory = Category::factory()->create();
        $emptyCategory->attributes()->attach($size);
        $this->catalog(['category' => $emptyCategory->slug, 'include_filters' => 1])->assertOk()->assertJsonPath('filters.attributes.0.options', []);
    }

    #[DataProvider('sizeTypes')]
    public function test_size_values_match_exact_json_strings_with_or_and_keep_legacy_substring_links(string $type): void
    {
        $category = Category::factory()->create();
        $size = $this->sizeAttribute($category, $type);
        $matches = [];
        foreach (['60x60', '30x60', '160x60', '60x600', ' 60x60 ', '60', 60, false, ['60x60']] as $value) {
            $product = Product::factory()->create(['category_id' => $category->id]);
            $this->value($product, $size, $value);
            if (in_array($value, ['60x60', '30x60'], true)) {
                $matches[] = $product->id;
            }
        }
        $response = $this->catalog(['category' => $category->slug, 'include_filters' => 1, 'attributes' => json_encode([$size->id => ['values' => ['60x60', '30x60']]])])
            ->assertOk()->assertJsonCount(2, 'data');
        self::assertEqualsCanonicalizing($matches, array_column($response->json('data'), 'id'));
        $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$size->id => ['values' => ['missing']]])])->assertOk()->assertJsonCount(0, 'data');
        $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$size->id => ['text' => '60x60']])])->assertOk()->assertJsonCount(4, 'data');
        $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$size->id => ['values' => [' 60x60 ']]])])->assertOk()->assertJsonCount(1, 'data');
        $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$size->id => ['values' => ['60']]])])->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_size_exact_values_keep_explicit_category_assignment_and_filterable_rules(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $size = $this->sizeAttribute($parent, 'string');
        $query = ['attributes' => json_encode([$size->id => ['values' => ['60x60']]])];
        $this->catalog($query)->assertUnprocessable();
        $this->catalog(['category' => $child->slug, ...$query])->assertUnprocessable();
        $size->update(['is_filterable' => false]);
        $this->catalog(['category' => $parent->slug, ...$query])->assertUnprocessable();
        $this->catalog(['category' => $parent->slug, 'include_filters' => 1])->assertOk()->assertJsonPath('filters.attributes', []);
    }

    public function test_other_string_attributes_accept_exact_values(): void
    {
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create(['type' => 'string', 'slug' => 'finish', 'is_filterable' => true]);
        $category->attributes()->attach($attribute);
        $this->value(Product::factory()->create(['category_id' => $category->id]), $attribute, 'matte');
        $this->value(Product::factory()->create(['category_id' => $category->id]), $attribute, 'matte finish');
        $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$attribute->id => ['values' => ['matte']]])])->assertOk()->assertJsonCount(1, 'data');
    }

    #[DataProvider('malformedSizeFilters')]
    public function test_rejects_malformed_size_selection(array $filter): void
    {
        $category = Category::factory()->create();
        $size = $this->sizeAttribute($category, 'string');
        $this->catalog(['category' => $category->slug, 'attributes' => json_encode([$size->id => $filter])])->assertUnprocessable();
    }

    public static function malformedSizeFilters(): array
    {
        return array_map(fn ($filter) => [$filter], [
            ['values' => []], ['values' => '60x60'], ['values' => null], ['values' => (object) ['a' => '60x60']],
            ['values' => [false]], ['values' => [60]], ['values' => [['60x60']]], ['values' => ['']], ['values' => ['   ']],
            ['values' => [(object) ['size' => '60x60']]],
            ['values' => [str_repeat('x', 256)]], ['values' => array_fill(0, 51, '60x60')], ['values' => ['60x60'], 'text' => '60'],
        ]);
    }

    public static function sizeTypes(): array
    {
        return [['string'], ['text']];
    }

    private function sizeAttribute(Category $category, string $type): Attribute
    {
        $attribute = Attribute::factory()->create(['slug' => 'razmer', 'type' => $type, 'is_filterable' => true]);
        $category->attributes()->attach($attribute);

        return $attribute;
    }

    private function value(Product $product, Attribute $attribute, mixed $value): void
    {
        ProductAttributeValue::query()->insert(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => json_encode($value, JSON_THROW_ON_ERROR)]);
    }

    private function catalog(array $query): TestResponse
    {
        return $this->getJson('/api/v1/catalog?'.http_build_query($query));
    }
}
