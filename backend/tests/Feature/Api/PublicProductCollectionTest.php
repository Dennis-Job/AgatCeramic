<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_is_projected_from_relation_with_only_public_media(): void
    {
        $image = Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'collection.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'title' => 'Collection', 'alt' => 'Collection room']);
        $brand = Brand::factory()->create(['country_code' => 'IT']);
        $collection = ProductCollection::factory()->create(['brand_id' => $brand->id, 'name' => 'Аллея', 'headline' => 'Headline', 'description' => 'Story', 'image_id' => $image->id]);
        $product = Product::factory()->create(['brand_id' => $brand->id, 'collection_id' => $collection->id]);
        $url = '/api/v1/products/'.$product->slug;
        $this->getJson($url)->assertOk()->assertJsonPath('data.collection.id', $collection->id)
            ->assertJsonPath('data.collection.name', 'Аллея')->assertJsonPath('data.collection.brand_slug', $brand->slug)
            ->assertJsonPath('data.collection.slug', $collection->slug)->assertJsonPath('data.collection.headline', 'Headline')
            ->assertJsonPath('data.collection.description', 'Story')->assertJsonPath('data.collection.country_code', 'IT')
            ->assertJsonPath('data.collection.image_alt', 'Collection room')->assertJsonMissingPath('data.collection.image_id');
        $image->update(['disk' => 'local']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.collection.image_url', null)->assertJsonPath('data.collection.image_alt', null);
        $collection->update(['is_active' => false]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.collection', null);
    }

    public function test_catalog_filter_reads_canonical_collection_in_brand_scope(): void
    {
        $collection = ProductCollection::factory()->create(['name' => 'Аллея']);
        $product = Product::factory()->create(['brand_id' => $collection->brand_id, 'collection_id' => $collection->id]);
        Product::factory()->create();
        $query = http_build_query(['brand' => $collection->brand->slug, 'collection' => $collection->name]);
        $this->getJson('/api/v1/catalog?'.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
        $collection->update(['is_active' => false]);
        $this->getJson('/api/v1/catalog?'.$query)->assertOk()->assertJsonCount(0, 'data');
    }
}
