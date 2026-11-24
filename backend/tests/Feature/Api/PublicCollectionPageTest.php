<?php

namespace Tests\Feature\Api;

use App\Http\Resources\PublicCollectionResource;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Queries\PublicCollectionQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicCollectionPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_uses_the_brand_and_collection_slugs_and_matches_its_product(): void
    {
        $brand = Brand::factory()->create(['name' => 'Kerama Marazzi', 'country_code' => 'IT', 'show_in_catalog_menu' => false]);
        $image = Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'collection.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'title' => 'Collection', 'alt' => 'Collection room']);
        $collection = ProductCollection::factory()->create(['brand_id' => $brand->id, 'name' => 'Аллея', 'slug' => 'alleya', 'headline' => 'Headline', 'description' => 'Story', 'image_id' => $image->id]);
        $otherCollection = ProductCollection::factory()->create(['slug' => 'alleya']);
        $product = Product::factory()->create(['brand_id' => $brand->id, 'collection_id' => $collection->id]);

        $profile = $this->getJson('/api/v1/brands/'.$brand->slug.'/collections/alleya')->assertOk()
            ->assertJsonPath('data.brand_name', $brand->name)->assertJsonMissingPath('data.image_id')->json('data');
        $productProjection = $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.brand.page_available', true)->json('data.collection');
        self::assertSame($productProjection, array_diff_key($profile, ['brand_name' => true]));
        $this->getJson('/api/v1/brands/'.$otherCollection->brand->slug.'/collections/alleya')->assertOk()->assertJsonPath('data.id', $otherCollection->id);

        foreach ([['disk' => 'local'], ['disk' => 'public', 'mime_type' => 'text/html']] as $overrides) {
            $image->update($overrides);
            $this->getJson('/api/v1/brands/'.$brand->slug.'/collections/alleya')->assertOk()->assertJsonPath('data.image_url', null)->assertJsonPath('data.image_alt', null);
            $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.collection.image_url', null)->assertJsonPath('data.collection.image_alt', null);
        }
        $brand->update(['is_active' => false]);
        $this->getJson('/api/v1/brands/'.$brand->slug.'/collections/alleya')->assertNotFound();
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.brand.page_available', false)->assertJsonPath('data.collection.id', $collection->id);
        $otherCollection->update(['is_active' => false]);
        $this->getJson('/api/v1/brands/'.$otherCollection->brand->slug.'/collections/alleya')->assertNotFound();
        $this->getJson('/api/v1/brands/missing/collections/alleya')->assertNotFound();
    }

    public function test_public_collection_listing_is_stable_and_includes_empty_collections(): void
    {
        $brand = Brand::factory()->create(['show_in_catalog_menu' => false]);
        foreach (range(1, 26) as $index) {
            ProductCollection::factory()->create(['brand_id' => $brand->id, 'name' => 'Collection '.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'slug' => 'collection-'.$index]);
        }
        ProductCollection::factory()->create(['brand_id' => $brand->id, 'is_active' => false]);
        ProductCollection::factory()->create(['brand_id' => Brand::factory()->create(['is_active' => false])->id]);

        $this->getJson('/api/v1/collections')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.slug', 'collection-1');
        $this->getJson('/api/v1/collections?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'collection-26');
        $this->getJson('/api/v1/collections?page[]=1')->assertUnprocessable();
    }

    public function test_public_collection_serialization_uses_eager_loaded_brand_and_image(): void
    {
        ProductCollection::factory()->count(3)->create();
        $collections = app(PublicCollectionQuery::class)->listing();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $data = PublicCollectionResource::collection($collections)->response()->getData(true);
            self::assertCount(3, $data['data']);
            self::assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_catalog_slug_scopes_products_metadata_and_category_counts(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id, 'uses_collections' => true]);
        $unrelated = Category::factory()->create();
        $attribute = Attribute::factory()->create(['type' => 'text', 'is_filterable' => true]);
        $attribute->categories()->attach($child);
        $collection = ProductCollection::factory()->create(['slug' => 'alleya']);
        $otherCollection = ProductCollection::factory()->create(['brand_id' => $collection->brand_id]);
        $sameSlugOtherBrand = ProductCollection::factory()->create(['slug' => $collection->slug]);
        $targetProduct = Product::factory()->create(['brand_id' => $collection->brand_id, 'collection_id' => $collection->id, 'category_id' => $child->id, 'price' => '25.50']);
        $otherProduct = Product::factory()->create(['brand_id' => $otherCollection->brand_id, 'collection_id' => $otherCollection->id, 'category_id' => $child->id, 'price' => '12.00']);
        $targetProduct->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => 'Inside']);
        $otherProduct->attributeValues()->create(['attribute_id' => $attribute->id, 'value' => 'Outside']);
        Product::factory()->create(['brand_id' => $collection->brand_id, 'category_id' => $unrelated->id, 'price' => '100.00']);
        Product::factory()->create(['brand_id' => $sameSlugOtherBrand->brand_id, 'collection_id' => $sameSlugOtherBrand->id, 'category_id' => $child->id]);

        $filters = ['brand' => $collection->brand->slug, 'collection_slug' => $collection->slug, 'category' => $child->slug, 'include_filters' => 1];
        $response = $this->getJson('/api/v1/catalog?'.http_build_query($filters))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $targetProduct->id)->assertJsonPath('category_navigation.total', 1)
            ->assertJsonCount(2, 'category_navigation.categories')->assertJsonPath('filters.price_range', ['min' => '25.50', 'max' => '25.50'])
            ->assertJsonPath('filters.attributes.0.options', [['value' => 'Inside', 'label' => 'Inside']]);
        self::assertEqualsCanonicalizing([$parent->id, $child->id], array_column($response->json('category_navigation.categories'), 'id'));
        $this->getJson('/api/v1/catalog?'.http_build_query([...$filters, 'price_from' => '30']))->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('category_navigation.total', 1);
        $this->getJson('/api/v1/catalog?'.http_build_query([...$filters, 'collection_slug' => 'missing']))->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('category_navigation.total', 0);
        $this->getJson('/api/v1/catalog?collection_slug=alleya')->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['brand']]]);
        $this->getJson('/api/v1/catalog?'.http_build_query([...$filters, 'collection' => 'Аллея']))->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['collection_slug']]]);
        $this->getJson('/api/v1/catalog?'.http_build_query([...$filters, 'collection_slug' => 'bad_slug']))->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['collection_slug']]]);
        $collection->brand->update(['is_active' => false]);
        $this->getJson('/api/v1/catalog?'.http_build_query($filters))->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('category_navigation.total', 0);
        $this->getJson('/api/v1/catalog?'.http_build_query(['brand' => $collection->brand->slug, 'collection' => $collection->name]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $targetProduct->id);
    }
}
