<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCatalogAttributeChoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seam_width_options_cover_the_entire_public_scope_and_exact_or_keeps_string_identifiers(): void
    {
        $category = Category::factory()->create();
        $seam = $this->attribute($category, 'string', 'shirina-shva');
        $brand = Brand::factory()->create();
        foreach (range(1, 25) as $i) {
            $this->value($this->product($category, $brand), $seam, $i === 25 ? '3-5 мм' : '1-3 мм');
        }
        $this->value($this->product($category, $brand), $seam, ' 01 мм ');
        $this->value($this->product($category, $brand), $seam, '01 мм');
        $this->value($this->product($category, $brand, ['is_active' => false]), $seam, 'hidden');
        $hidden = Category::factory()->create(['parent_id' => $category->id, 'is_active' => false]);
        $this->value($this->product($hidden, $brand), $seam, 'hidden category');
        $foreign = Category::factory()->create();
        $this->value($this->product($foreign, $brand), $seam, 'foreign');
        $seam->options()->create(['value' => 'unused', 'label' => 'Unused historical option']);

        $this->catalog($category, ['include_filters' => 1, 'category_descendants' => 1])
            ->assertOk()->assertJsonCount(24, 'data')->assertJsonPath('filters.attributes.0.options', [
                ['value' => ' 01 мм ', 'label' => ' 01 мм '], ['value' => '01 мм', 'label' => '01 мм'],
                ['value' => '1-3 мм', 'label' => '1-3 мм'], ['value' => '3-5 мм', 'label' => '3-5 мм'],
            ]);
        $this->selection($category, $seam, ['values' => ['3-5 мм', '01 мм']])->assertOk()->assertJsonPath('meta.total', 2);
        $this->selection($category, $seam, ['values' => [' 01 мм ']])->assertOk()->assertJsonPath('meta.total', 1);
        $this->selection($category, $seam, ['text' => '3-5'])->assertOk()->assertJsonPath('meta.total', 1);
        $this->selection($foreign, $seam, ['values' => ['foreign']])->assertUnprocessable();
        $seam->update(['is_filterable' => false]);
        $this->selection($category, $seam, ['values' => ['1-3 мм']])->assertUnprocessable();
    }

    #[DataProvider('numericCases')]
    public function test_numeric_options_normalize_equivalent_numbers_and_exact_or_never_coerces_junk_or_booleans(string $type, array $stored, array $options, array $selected, int $matches): void
    {
        $category = Category::factory()->create();
        $attribute = $this->attribute($category, $type);
        $brand = Brand::factory()->create();
        foreach ([...$stored, false, true, null, 'junk', 'false', '1.5mm', 'NaN', 'Infinity', [], ['0']] as $value) {
            $this->value($this->product($category, $brand), $attribute, $value);
        }
        $response = $this->catalog($category, ['include_filters' => 1])->assertOk();
        self::assertSame($options, array_column($response->json('filters.attributes.0.options'), 'value'));
        $this->selection($category, $attribute, ['values' => $selected])->assertOk()->assertJsonPath('meta.total', $matches);
        $this->selection($category, $attribute, ['min' => '0', 'max' => '0'])->assertOk()->assertJsonPath('meta.total', 2);
    }

    public static function numericCases(): array
    {
        return [
            'integer' => ['integer', [0, '0', -2, '-2.0', 5, '5', 10, 2.5], ['-2', '0', '5', '10'], ['0', '-2'], 4],
            'decimal' => ['decimal', [0, '-0.00', -1.5, '-1.500', 2.25, '2.2500', 1.0e-7, '1e-7'], ['-1.5', '0', '0.0000001', '2.25'], ['0', '-1.50'], 4],
        ];
    }

    public function test_integer_extremes_remain_exact_and_literal_backslash_null_text_is_preserved(): void
    {
        $category = Category::factory()->create();
        $integer = $this->attribute($category, 'integer');
        $text = $this->attribute($category, 'text');
        foreach ([PHP_INT_MIN, PHP_INT_MAX] as $value) {
            $product = $this->product($category);
            $this->value($product, $integer, $value);
            $this->value($product, $text, 'literal \\u0000');
            $this->selection($category, $integer, ['values' => [(string) $value]])->assertOk()->assertJsonPath('meta.total', 1);
        }
        $metadata = collect($this->catalog($category, ['include_filters' => 1])->assertOk()->json('filters.attributes'))->keyBy('id');
        self::assertSame([(string) PHP_INT_MIN, (string) PHP_INT_MAX], array_column($metadata[$integer->id]['options'], 'value'));
        self::assertSame([['value' => 'literal \\u0000', 'label' => 'literal \\u0000']], $metadata[$text->id]['options']);
        $this->selection($category, $text, ['values' => ['literal \\u0000']])->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_finite_decimal_extremes_emit_plain_selectable_values_without_float_canonicalization(): void
    {
        $category = Category::factory()->create();
        $attribute = $this->attribute($category, 'decimal');
        $brand = Brand::factory()->create();
        foreach ([PHP_FLOAT_MAX, 5.0e-324, '9007199254740992.1', '9007199254740992.2'] as $value) {
            $this->value($this->product($category, $brand), $attribute, $value);
        }
        $values = array_column($this->catalog($category, ['include_filters' => 1])->assertOk()->json('filters.attributes.0.options'), 'value');
        self::assertContains('0.'.str_repeat('0', 323).'5', $values);
        self::assertContains('17976931348623157'.str_repeat('0', 292), $values);
        self::assertContains('9007199254740992.1', $values);
        self::assertContains('9007199254740992.2', $values);
        foreach ($values as $value) {
            $this->selection($category, $attribute, ['values' => [$value]])->assertOk();
        }
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->selection($category, $attribute, ['values' => ['9007199254740992.1']])->assertOk()->assertJsonPath('meta.total', 1);
        }
    }

    public function test_date_options_reject_invalid_calendar_dates_and_keep_legacy_ranges(): void
    {
        $category = Category::factory()->create();
        $attribute = $this->attribute($category, 'date');
        $brand = Brand::factory()->create();
        foreach (['2026-01-01', '2026-01-01', '2026-02-28', '2026-02-30', "2026-01-01\0", 'today', 20260101, false] as $value) {
            $this->value($this->product($category, $brand), $attribute, $value);
        }
        $this->catalog($category, ['include_filters' => 1])->assertOk()->assertJsonPath('filters.attributes.0.options', [
            ['value' => '2026-01-01', 'label' => '2026-01-01'], ['value' => '2026-02-28', 'label' => '2026-02-28'],
        ]);
        $this->selection($category, $attribute, ['values' => ['2026-01-01', '2026-02-28']])->assertOk()->assertJsonPath('meta.total', 3);
        $this->selection($category, $attribute, ['min' => '2026-02-28', 'max' => '2026-02-28'])->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_long_text_option_remains_selectable_even_when_json_escaping_exceeds_the_old_payload_limit(): void
    {
        $category = Category::factory()->create();
        $attribute = $this->attribute($category, 'text');
        $text = str_repeat("\n", 9999).'x';
        $this->value($this->product($category), $attribute, $text);
        $this->value($this->product($category), $attribute, str_repeat('x', 10001));
        $this->catalog($category, ['include_filters' => 1])->assertOk()->assertJsonPath('filters.attributes.0.options', [['value' => $text, 'label' => $text]]);
        $this->selection($category, $attribute, ['values' => [$text]])->assertOk()->assertJsonPath('meta.total', 1);
        $this->selection($category, $attribute, ['text' => 'x'])->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_boolean_options_only_expose_actual_typed_values(): void
    {
        $category = Category::factory()->create();
        $attribute = $this->attribute($category, 'boolean');
        $brand = Brand::factory()->create();
        foreach ([false, 'false', 'true', 0] as $value) {
            $this->value($this->product($category, $brand), $attribute, $value);
        }
        $this->value($this->product($category, $brand, ['is_active' => false]), $attribute, true);
        $this->catalog($category, ['include_filters' => 1])->assertOk()->assertJsonPath('filters.attributes.0.options', [['value' => 'false', 'label' => 'Нет']]);
        $this->selection($category, $attribute, ['values' => ['false']])->assertOk()->assertJsonPath('meta.total', 1);
    }

    #[DataProvider('scalarTextTypes')]
    public function test_scalar_options_ignore_actual_nul_but_preserve_literal_escapes_and_emoji(string $type): void
    {
        $category = Category::factory()->create();
        $attribute = $this->attribute($category, $type);
        foreach (["bad\0tail", 'literal \\u0000', 'Цвет😀'] as $value) {
            $this->value($this->product($category), $attribute, $value);
        }
        $this->catalog($category, ['include_filters' => 1])->assertOk()->assertJsonPath('filters.attributes.0.options', [
            ['value' => 'literal \\u0000', 'label' => 'literal \\u0000'], ['value' => 'Цвет😀', 'label' => 'Цвет😀'],
        ]);
        foreach (['literal \\u0000', 'Цвет😀'] as $value) {
            $this->selection($category, $attribute, ['values' => [$value]])->assertOk()->assertJsonPath('meta.total', 1);
        }
    }

    public static function scalarTextTypes(): array
    {
        return [['string'], ['text']];
    }

    #[DataProvider('scalarTextTypes')]
    public function test_scalar_choices_reject_nul_before_querying_a_valid_prefix(string $type): void
    {
        $category = Category::factory()->create();
        $attribute = $this->attribute($category, $type);
        $this->value($this->product($category), $attribute, '1-10');

        $this->selection($category, $attribute, ['values' => ["1-10\0bad"]])->assertUnprocessable();
    }

    #[DataProvider('malformedSelections')]
    public function test_rejects_wrong_types_invalid_values_and_mixed_legacy_shapes(string $type, array $filter): void
    {
        $category = Category::factory()->create();
        $attribute = $this->attribute($category, $type);
        $this->selection($category, $attribute, $filter)->assertUnprocessable();
    }

    public static function malformedSelections(): array
    {
        return [
            ['integer', ['values' => ['1.5']]], ['decimal', ['values' => ['junk']]], ['decimal', ['values' => ['Infinity']]],
            ['decimal', ['values' => ['1e9999']]], ['integer', ['values' => [0]]], ['date', ['values' => ['2026-02-30']]],
            ['date', ['values' => ["2026-01-01\0"]]],
            ['date', ['values' => ['2026-1-1']]], ['string', ['values' => [false]]], ['decimal', ['values' => (object) ['a' => '1']]],
            ['date', ['values' => [(object) ['value' => '2026-01-01']]]], ['integer', ['values' => []]],
            ['text', ['values' => [str_repeat('x', 10001)]]], ['string', ['values' => [str_repeat('x', 256)]]],
            ['text', ['values' => array_fill(0, 7, str_repeat('x', 10000))]],
            ['string', ['values' => array_fill(0, 51, 'x')]], ['text', ['values' => ['x'], 'text' => 'x']],
            ['integer', ['values' => ['1'], 'min' => '0']], ['date', ['values' => ['2026-01-01'], 'max' => '2026-12-31']],
        ];
    }

    private function attribute(Category $category, string $type, ?string $slug = null): Attribute
    {
        $attribute = Attribute::factory()->create(['type' => $type, 'is_filterable' => true, ...($slug === null ? [] : ['slug' => $slug])]);
        $category->attributes()->attach($attribute);

        return $attribute;
    }

    private function product(Category $category, ?Brand $brand = null, array $attributes = []): Product
    {
        return Product::factory()->create(['category_id' => $category->id, ...($brand === null ? [] : ['brand_id' => $brand->id]), ...$attributes]);
    }

    private function value(Product $product, Attribute $attribute, mixed $value): void
    {
        ProductAttributeValue::query()->insert(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => json_encode($value, JSON_THROW_ON_ERROR)]);
    }

    private function catalog(Category $category, array $query = []): TestResponse
    {
        return $this->getJson('/api/v1/catalog?'.http_build_query(['category' => $category->slug, ...$query]));
    }

    private function selection(Category $category, Attribute $attribute, array $filter): TestResponse
    {
        return $this->catalog($category, ['attributes' => json_encode([$attribute->id => $filter], JSON_THROW_ON_ERROR)]);
    }
}
