<?php

namespace Tests\Feature\Api;

use App\Http\Resources\PublicCatalogProductResource;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Queries\PublicCatalogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicCatalogCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_cards_expose_their_own_public_size_and_stock_without_inventory_details(): void
    {
        $size = Attribute::factory()->create(['name' => 'Размер', 'slug' => 'razmer', 'type' => 'select', 'unit' => 'мм']);
        $size->options()->create(['value' => 'format-a', 'label' => '300х300']);
        $sized = Product::factory()->create(['name' => 'A Товар 999x999', 'is_on_sale' => true, 'stock_quantity' => 5, 'description' => 'Описание сохранено']);
        $blank = Product::factory()->create(['name' => 'B Пустой размер', 'stock_quantity' => 0, 'price' => null, 'brand_id' => null]);
        $missing = Product::factory()->create(['name' => 'C Без характеристики 600x600', 'stock_quantity' => null]);
        $this->sizeValue($sized, $size, 'format-a');
        $this->sizeValue($blank, $size, '   ');

        $this->getJson('/api/v1/catalog')->assertOk()
            ->assertJsonPath('data.0.id', $sized->id)
            ->assertJsonPath('data.0.size', '300х300 мм')
            ->assertJsonPath('data.0.in_stock', true)
            ->assertJsonPath('data.0.description', 'Описание сохранено')
            ->assertJsonPath('data.1.id', $blank->id)
            ->assertJsonPath('data.1.size', null)
            ->assertJsonPath('data.1.in_stock', false)
            ->assertJsonPath('data.1.price', null)
            ->assertJsonPath('data.1.brand', null)
            ->assertJsonPath('data.2.id', $missing->id)
            ->assertJsonPath('data.2.size', null)
            ->assertJsonPath('data.2.in_stock', false)
            ->assertJsonMissingPath('data.0.stock_quantity')
            ->assertJsonMissingPath('data.0.sku');

        $size->update(['is_visible_on_product_page' => false]);
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonPath('data.0.size', null);
    }

    public function test_catalog_card_serialization_uses_eager_loaded_relations_without_queries(): void
    {
        $size = Attribute::factory()->create(['name' => 'Размер', 'slug' => 'razmer', 'type' => 'select', 'unit' => null]);
        $size->options()->create(['value' => 'format-a', 'label' => '30×30']);
        foreach (range(1, 3) as $index) {
            $product = Product::factory()->create(['name' => 'Товар '.$index]);
            $this->sizeValue($product, $size, 'format-a');
        }
        $products = app(PublicCatalogQuery::class)->products();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $payload = PublicCatalogProductResource::collection($products)->response()->getData(true);
            $this->assertCount(3, $payload['data']);
            $this->assertSame(['30×30', '30×30', '30×30'], array_column($payload['data'], 'size'));
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    private function sizeValue(Product $product, Attribute $attribute, string $value): void
    {
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => $value]);
    }
}
