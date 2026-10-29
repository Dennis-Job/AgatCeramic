<?php

namespace Tests\Feature\Api;

use App\Http\Resources\PublicProductDetailResource;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductGroup;
use App\Models\ProductImage;
use App\Models\ProductRelation;
use App\Queries\PublicProductQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_has_ordered_public_images_resolved_visible_attributes_and_no_inventory_or_admin_fields(): void
    {
        $logo = Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'media/brand.png', 'mime_type' => 'image/png', 'size' => 10, 'title' => 'Brand', 'alt' => 'Логотип бренда']);
        $brand = Brand::factory()->create(['logo_id' => $logo->id]);
        $product = $this->product(['brand_id' => $brand->id, 'price' => '1342.00', 'unit' => 'square_meter', 'stock_quantity' => 10]);
        $last = $this->image($product, 'last.jpg', 2);
        $first = $this->image($product, 'first.jpg', 0, true);
        $this->image($product, 'private.jpg', 1, false, 'local');
        $color = $this->attribute('Цвет', 'tsvet', 'select', 0);
        $color->options()->create(['value' => '#808080', 'label' => 'Серый', 'sort_order' => 0]);
        $this->value($product, $color, '#808080');
        $collection = $this->attribute('Коллекция', 'kollektsiya', 'string', 1);
        $this->value($product, $collection, 'Аллея');
        $hidden = $this->attribute('Внутреннее', 'hidden', 'string', 2, false);
        $this->value($product, $hidden, 'Не публиковать');

        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', $product->sku)
            ->assertJsonPath('data.price', '1342.00')
            ->assertJsonPath('data.in_stock', true)
            ->assertJsonPath('data.brand.logo_alt', 'Логотип бренда')
            ->assertJsonPath('data.images.0.id', $first->id)
            ->assertJsonPath('data.images.1.id', $last->id)
            ->assertJsonCount(2, 'data.images')
            ->assertJsonCount(2, 'data.attributes')
            ->assertJsonPath('data.attributes.0.value', '#808080')
            ->assertJsonPath('data.attributes.0.display_value', 'Серый')
            ->assertJsonPath('data.collection.name', 'Аллея')
            ->assertJsonPath('data.collection.brand_slug', $brand->slug)
            ->assertJsonMissingPath('data.stock_quantity')
            ->assertJsonMissingPath('data.max_quantity')
            ->assertJsonMissingPath('data.created_at')
            ->assertJsonMissingPath('data.images.0.disk')
            ->assertJsonMissing(['Не публиковать']);
    }

    public function test_detail_rejects_hidden_products_and_categories_but_allows_an_off_sale_page(): void
    {
        $inactive = $this->product(['is_active' => false]);
        $hiddenCategory = Category::factory()->create(['is_active' => false]);
        $hidden = $this->product(['category_id' => $hiddenCategory->id]);
        $offSale = $this->product(['is_on_sale' => false]);
        foreach ([$inactive->slug, $hidden->slug, 'missing-product'] as $slug) {
            $this->getJson('/api/v1/products/'.$slug)->assertNotFound();
        }
        $this->getJson('/api/v1/products/'.$offSale->slug)->assertOk()->assertJsonPath('data.is_on_sale', false);
    }

    public function test_nullable_product_data_and_missing_media_remain_honest(): void
    {
        $product = $this->product(['brand_id' => null, 'description' => null, 'price' => null, 'stock_quantity' => null]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.brand', null)->assertJsonPath('data.description', null)
            ->assertJsonPath('data.price', null)->assertJsonPath('data.in_stock', false)
            ->assertJsonPath('data.image_url', null)->assertJsonPath('data.images', [])
            ->assertJsonPath('data.attributes', [])->assertJsonPath('data.collection', null)
            ->assertJsonPath('data.group', null)->assertJsonPath('data.related', [])
            ->assertJsonPath('data.recommended', []);
    }

    public function test_real_group_members_keep_their_identity_and_unavailable_members_are_filtered(): void
    {
        $product = $this->product();
        $sibling = $this->product();
        $hidden = $this->product(['is_active' => false]);
        $offSale = $this->product(['is_on_sale' => false]);
        $hiddenCategory = $this->product(['category_id' => Category::factory()->create(['is_active' => false])->id]);
        $color = $this->attribute('Цвет', 'tsvet', 'select');
        $color->options()->create(['value' => 'light', 'label' => 'Светлый', 'sort_order' => 0]);
        $this->value($sibling, $color, 'light');
        $group = ProductGroup::factory()->create();
        $group->axes()->attach($color->id, ['sort_order' => 0]);
        $group->products()->attach([$product->id, $sibling->id, $hidden->id, $offSale->id, $hiddenCategory->id]);

        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.group.axes.0.slug', 'tsvet')
            ->assertJsonCount(2, 'data.group.products')
            ->assertJsonPath('data.group.products.0.id', $product->id)
            ->assertJsonPath('data.group.products.1.id', $sibling->id)
            ->assertJsonPath('data.group.products.1.slug', $sibling->slug)
            ->assertJsonPath('data.group.products.1.axis_values.0.display_value', 'Светлый')
            ->assertJsonMissingPath('data.group.products.1.stock_quantity');
    }

    public function test_only_ordered_outgoing_public_relations_are_exposed_in_their_actual_type(): void
    {
        $product = $this->product();
        $later = $this->product();
        $first = $this->product();
        $recommended = $this->product();
        $hidden = $this->product(['is_active' => false]);
        $offSale = $this->product(['is_on_sale' => false]);
        $hiddenCategory = $this->product(['category_id' => Category::factory()->create(['is_active' => false])->id]);
        $incoming = $this->product();
        foreach ([[$later, 'related', 5], [$first, 'related', 1], [$recommended, 'recommended', 2], [$hidden, 'related', 0], [$offSale, 'recommended', 0], [$hiddenCategory, 'related', 0]] as [$target, $type, $order]) {
            ProductRelation::query()->create(['product_id' => $product->id, 'related_product_id' => $target->id, 'type' => $type, 'sort_order' => $order]);
        }
        ProductRelation::query()->create(['product_id' => $incoming->id, 'related_product_id' => $product->id, 'type' => 'related', 'sort_order' => 0]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonCount(2, 'data.related')->assertJsonPath('data.related.0.id', $first->id)
            ->assertJsonPath('data.related.1.id', $later->id)
            ->assertJsonCount(1, 'data.recommended')->assertJsonPath('data.recommended.0.id', $recommended->id);
    }

    public function test_multiselect_boolean_numeric_and_unknown_option_display_values_are_resolved(): void
    {
        $product = $this->product();
        $multi = $this->attribute('Назначение', 'usage', 'multiselect', 0);
        $multi->options()->createMany([['value' => 'a', 'label' => 'Пол'], ['value' => 'b', 'label' => 'Стена']]);
        $this->value($product, $multi, ['b', 'a']);
        $boolean = $this->attribute('Морозостойкость', 'frost', 'boolean', 1);
        $this->value($product, $boolean, false);
        $number = $this->attribute('Толщина', 'thickness', 'decimal', 2);
        $this->value($product, $number, 8.5);
        $unknown = $this->attribute('Историческое', 'legacy', 'select', 3);
        $this->value($product, $unknown, 'missing');
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.attributes.0.display_value', 'Стена, Пол')
            ->assertJsonPath('data.attributes.1.display_value', 'Нет')
            ->assertJsonPath('data.attributes.2.display_value', '8,5')
            ->assertJsonPath('data.attributes.3.display_value', 'missing');
    }

    public function test_catalog_filters_apply_before_pagination_and_scope_collection_by_brand_without_changing_legacy_projection(): void
    {
        $brand = Brand::factory()->create();
        $otherBrand = Brand::factory()->create();
        $category = Category::factory()->create(['is_active' => true]);
        $collection = $this->attribute('Коллекция', 'kollektsiya', 'string');
        $wanted = $this->product(['brand_id' => $brand->id, 'category_id' => $category->id]);
        $this->value($wanted, $collection, 'Аллея');
        $sameName = $this->product(['brand_id' => $otherBrand->id, 'category_id' => $category->id]);
        $this->value($sameName, $collection, 'Аллея');
        $differentCollection = $this->product(['brand_id' => $brand->id, 'category_id' => $category->id]);
        $this->value($differentCollection, $collection, 'Аллея 2');
        $anotherCategory = $this->product(['brand_id' => $brand->id]);
        $this->value($anotherCategory, $collection, 'Аллея');
        $query = http_build_query(['category' => $category->slug, 'brand' => $brand->slug, 'collection' => 'Аллея']);
        $this->getJson('/api/v1/catalog?'.$query)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wanted->id)->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 24)->assertJsonMissingPath('data.0.sku')
            ->assertJsonMissingPath('data.0.stock_quantity');
        $this->getJson('/api/v1/catalog?brand='.$brand->slug)->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/catalog?category='.$category->slug)->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/v1/catalog?brand=missing')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_catalog_filter_validation_rejects_unscoped_collections_and_malformed_parameters(): void
    {
        foreach (['collection=Аллея', 'brand[]=bad', 'category[]=bad', 'page=0', 'page=1.5', 'brand=bad%20slug'] as $query) {
            $this->getJson('/api/v1/catalog?'.$query)->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        }
    }

    public function test_detail_serialization_never_queries_for_each_image_attribute_sibling_or_relation(): void
    {
        $product = $this->product();
        $sibling = $this->product();
        $color = $this->attribute('Цвет', 'tsvet', 'select');
        $color->options()->create(['value' => 'gray', 'label' => 'Серый']);
        $this->value($product, $color, 'gray');
        $this->value($sibling, $color, 'gray');
        $this->image($product, 'product.jpg', 0, true);
        $this->image($sibling, 'sibling.jpg', 0, true);
        $group = ProductGroup::factory()->create();
        $group->axes()->attach($color->id, ['sort_order' => 0]);
        $group->products()->attach([$product->id, $sibling->id]);
        $related = $this->product();
        ProductRelation::query()->create(['product_id' => $product->id, 'related_product_id' => $related->id, 'type' => 'related', 'sort_order' => 0]);
        $loaded = app(PublicProductQuery::class)->find($product->slug);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $payload = (new PublicProductDetailResource($loaded))->response()->getData(true);
            $this->assertCount(2, $payload['data']['group']['products']);
            $this->assertCount(1, $payload['data']['related']);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    /** @param array<string, mixed> $values */
    private function product(array $values = []): Product
    {
        return Product::factory()->create(['is_on_sale' => true, ...$values]);
    }

    private function image(Product $product, string $path, int $order, bool $primary = false, string $disk = 'public'): ProductImage
    {
        return $product->images()->create(['disk' => $disk, 'path' => $path, 'mime_type' => 'image/jpeg', 'size' => 10, 'alt' => $product->name, 'is_primary' => $primary, 'sort_order' => $order]);
    }

    private function attribute(string $name, string $slug, string $type, int $order = 0, bool $visible = true): Attribute
    {
        return Attribute::query()->create(['name' => $name, 'slug' => $slug, 'type' => $type, 'is_visible_on_product_page' => $visible, 'sort_order' => $order]);
    }

    private function value(Product $product, Attribute $attribute, mixed $value): void
    {
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => $value]);
    }
}
