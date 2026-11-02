<?php

namespace Tests\Feature\Api;

use App\Http\Resources\PublicProductAttributeResource;
use App\Http\Resources\PublicProductDetailResource;
use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductCollection;
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

    public function test_detail_exposes_owned_attribute_groups_without_reordering_values_or_exposing_hidden_groups(): void
    {
        $product = $this->product();
        $firstGroup = AttributeGroup::factory()->create(['name' => 'Основные', 'sort_order' => 20]);
        $secondGroup = AttributeGroup::factory()->create(['name' => 'Технические', 'sort_order' => 10]);
        $hiddenGroup = AttributeGroup::factory()->create(['name' => 'Внутренние']);
        $first = $this->attribute('Первое', 'first', 'string', 0);
        $first->update(['attribute_group_id' => $firstGroup->id]);
        $ungrouped = $this->attribute('Без группы', 'ungrouped', 'string', 1);
        $second = $this->attribute('Второе', 'second', 'integer', 2);
        $second->update(['attribute_group_id' => $secondGroup->id]);
        $hidden = $this->attribute('Скрытое', 'hidden', 'string', 3, false);
        $hidden->update(['attribute_group_id' => $hiddenGroup->id]);
        foreach ([[$first, 'А'], [$ungrouped, 'Б'], [$second, 12], [$hidden, 'Секрет']] as [$attribute, $value]) {
            $this->value($product, $attribute, $value);
        }

        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonCount(3, 'data.attributes')
            ->assertJsonPath('data.attributes.0.id', $first->id)
            ->assertJsonPath('data.attributes.0.group', ['id' => $firstGroup->id, 'name' => 'Основные', 'sort_order' => 20])
            ->assertJsonPath('data.attributes.1.id', $ungrouped->id)
            ->assertJsonPath('data.attributes.1.group', null)
            ->assertJsonPath('data.attributes.2.id', $second->id)
            ->assertJsonPath('data.attributes.2.group', ['id' => $secondGroup->id, 'name' => 'Технические', 'sort_order' => 10])
            ->assertJsonPath('data.attributes.2.value', 12)
            ->assertJsonPath('data.attributes.2.display_value', '12')
            ->assertJsonMissing(['name' => 'Внутренние'])
            ->assertJsonMissing(['name' => 'Скрытое']);
    }

    public function test_attribute_group_projection_preserves_distinct_ids_when_names_match_without_queries(): void
    {
        // Persisted group names are unique; the projection still identifies groups by ID.
        $groups = [
            new AttributeGroup(['name' => 'Свойства', 'sort_order' => 10]),
            new AttributeGroup(['name' => 'Свойства', 'sort_order' => 20]),
        ];
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            foreach ($groups as $index => $group) {
                $group->id = $index + 101;
                $attribute = new Attribute(['name' => 'Характеристика', 'slug' => 'property', 'type' => 'string']);
                $attribute->id = $index + 1;
                $attribute->setRelation('group', $group);
                $value = (new ProductAttributeValue(['value' => 'Значение']))->setRelation('attribute', $attribute);
                $data = (new PublicProductAttributeResource($value))->resolve();

                $this->assertSame(['id' => $index + 101, 'name' => 'Свойства', 'sort_order' => ($index + 1) * 10], $data['group']);
            }
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

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
        $this->value($product, $collection, 'Historical collection');
        $product->update(['collection_id' => ProductCollection::factory()->create(['brand_id' => $brand->id, 'name' => 'Аллея'])->id]);
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
            ->assertJsonCount(1, 'data.attributes')
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
        $this->value($wanted, $collection, 'Stale history');
        $canonical = ProductCollection::factory()->create(['brand_id' => $brand->id, 'name' => 'Аллея']);
        $wanted->update(['collection_id' => $canonical->id]);
        $sameName = $this->product(['brand_id' => $otherBrand->id, 'category_id' => $category->id]);
        $this->value($sameName, $collection, 'Аллея');
        $sameName->update(['collection_id' => ProductCollection::factory()->create(['brand_id' => $otherBrand->id, 'name' => 'Аллея'])->id]);
        $differentCollection = $this->product(['brand_id' => $brand->id, 'category_id' => $category->id]);
        $this->value($differentCollection, $collection, 'Аллея 2');
        $differentCollection->update(['collection_id' => ProductCollection::factory()->create(['brand_id' => $brand->id, 'name' => 'Аллея 2'])->id]);
        $anotherCategory = $this->product(['brand_id' => $brand->id]);
        $this->value($anotherCategory, $collection, 'Аллея');
        $anotherCategory->update(['collection_id' => $canonical->id]);
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
        $attributeGroup = AttributeGroup::factory()->create(['name' => 'Характеристики', 'sort_order' => 5]);
        $color->update(['attribute_group_id' => $attributeGroup->id]);
        $color->options()->create(['value' => 'gray', 'label' => 'Серый']);
        $this->value($product, $color, 'gray');
        $this->value($sibling, $color, 'gray');
        $this->image($product, 'product.jpg', 0, true);
        $this->image($sibling, 'sibling.jpg', 0, true);
        $group = ProductGroup::factory()->create();
        $group->axes()->attach($color->id, ['sort_order' => 0]);
        $group->products()->attach([$product->id, $sibling->id]);
        $related = $this->product();
        $size = $this->attribute('Размер', 'razmer', 'string');
        $size->update(['unit' => 'мм', 'attribute_group_id' => $attributeGroup->id]);
        $this->value($product, $size, '600х600');
        $this->value($sibling, $size, '400х400');
        $this->value($related, $size, '300х300');
        ProductRelation::query()->create(['product_id' => $product->id, 'related_product_id' => $related->id, 'type' => 'related', 'sort_order' => 0]);
        $loaded = app(PublicProductQuery::class)->find($product->slug);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $payload = (new PublicProductDetailResource($loaded))->response()->getData(true);
            $this->assertCount(2, $payload['data']['group']['products']);
            $this->assertCount(1, $payload['data']['related']);
            $this->assertSame(['id' => $attributeGroup->id, 'name' => 'Характеристики', 'sort_order' => 5], $payload['data']['attributes'][0]['group']);
            $this->assertSame('600х600 мм', $payload['data']['size']);
            $this->assertSame('400х400 мм', $payload['data']['group']['products'][1]['size']);
            $this->assertSame('300х300 мм', $payload['data']['related'][0]['size']);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_recommended_size_resolves_its_own_visible_attribute_and_omits_missing_or_blank_values(): void
    {
        $product = $this->product(['name' => 'Размер в названии 100x100']);
        $sized = $this->product(['name' => 'Размер в названии 200x200']);
        $blank = $this->product();
        $missing = $this->product(['name' => 'Только название 300x300']);
        $size = $this->attribute('Размер', 'razmer', 'select');
        $size->update(['unit' => 'мм']);
        $size->options()->create(['value' => 'format-a', 'label' => '300х300']);
        $this->value($product, $size, '600х600');
        $this->value($sized, $size, 'format-a');
        $this->value($blank, $size, '   ');
        foreach ([$sized, $blank, $missing] as $index => $target) {
            ProductRelation::query()->create(['product_id' => $product->id, 'related_product_id' => $target->id, 'type' => 'recommended', 'sort_order' => $index]);
        }

        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.size', '600х600 мм')
            ->assertJsonPath('data.recommended.0.size', '300х300 мм')
            ->assertJsonPath('data.recommended.1.size', null)
            ->assertJsonPath('data.recommended.2.size', null);

        $size->update(['unit' => null]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.recommended.0.size', '300х300');

        $size->update(['is_visible_on_product_page' => false]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.size', null)
            ->assertJsonPath('data.recommended.0.size', null);
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
