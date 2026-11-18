<?php

namespace Tests\Feature\Api;

use App\Http\Resources\PublicBrandResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Queries\PublicBrandQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicBrandPageTest extends TestCase
{
    use RefreshDatabase;

    private int $mediaNumber = 0;

    public function test_catalog_brand_choices_only_offer_brands_with_public_pages_without_hiding_their_products(): void
    {
        $category = Category::factory()->create();
        $active = Brand::factory()->create();
        $inactive = Brand::factory()->create(['is_active' => false]);
        foreach ([$active, $inactive] as $brand) {
            Product::factory()->create(['brand_id' => $brand->id, 'category_id' => $category->id]);
        }
        $this->getJson('/api/v1/catalog?include_filters=1&category='.$category->slug)
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('filters.brands', [['id' => $active->id, 'name' => $active->name, 'slug' => $active->slug]]);
    }

    public function test_public_brand_profile_uses_the_slug_and_existing_description_and_image(): void
    {
        $image = $this->image();
        $brand = Brand::factory()->create(['name' => 'Kerama Marazzi', 'slug' => 'kerama-marazzi', 'description' => 'Описание бренда', 'logo_id' => $image->id, 'show_in_catalog_menu' => false]);
        $this->getJson('/api/v1/brands/kerama-marazzi')->assertOk()->assertExactJson(['data' => [
            'id' => $brand->id, 'name' => 'Kerama Marazzi', 'slug' => 'kerama-marazzi',
            'description' => 'Описание бренда', 'logo_url' => Storage::disk('public')->url($image->path), 'logo_alt' => 'Логотип бренда',
        ]]);
    }

    public function test_unknown_and_inactive_brands_are_not_public(): void
    {
        Brand::factory()->create(['slug' => 'inactive', 'is_active' => false, 'show_in_catalog_menu' => true]);
        foreach (['missing', 'inactive', 'invalid_slug', '123'] as $slug) {
            $this->getJson('/api/v1/brands/'.$slug)->assertNotFound();
        }
    }

    public function test_empty_brand_is_public_but_private_and_nonimage_media_are_not_exposed(): void
    {
        $brand = Brand::factory()->create(['description' => null, 'logo_id' => null]);
        $this->getJson('/api/v1/brands/'.$brand->slug)->assertOk()
            ->assertJsonPath('data.description', null)->assertJsonPath('data.logo_url', null)->assertJsonPath('data.logo_alt', $brand->name);
        foreach ([['disk' => 'local'], ['kind' => 'document', 'mime_type' => 'application/pdf'], ['mime_type' => 'text/html']] as $overrides) {
            $brand->update(['logo_id' => $this->image($overrides)->id]);
            $this->getJson('/api/v1/brands/'.$brand->slug)->assertOk()
                ->assertJsonPath('data.logo_url', null)->assertJsonPath('data.logo_alt', $brand->name)
                ->assertJsonMissingPath('data.logo_id')->assertJsonMissingPath('data.logo');
        }
    }

    public function test_public_brand_listing_is_stably_paginated_and_independent_of_alphabet_selection(): void
    {
        $brands = [];
        foreach (range(1, 27) as $index) {
            $brands[] = Brand::factory()->create(['name' => 'Brand '.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'slug' => 'brand-'.$index, 'show_in_catalog_menu' => false]);
        }
        Brand::factory()->create(['name' => 'Hidden', 'is_active' => false]);
        $this->getJson('/api/v1/brands')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 27)
            ->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.id', $brands[0]->id);
        $this->getJson('/api/v1/brands?page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $brands[25]->id);
        $this->getJson('/api/v1/brands?page=0')->assertUnprocessable();
        $this->getJson('/api/v1/brands?page[]=1')->assertUnprocessable();
    }

    public function test_brand_resource_uses_eager_loaded_media_without_serialization_queries(): void
    {
        Brand::factory()->count(3)->create(['logo_id' => $this->image()->id]);
        $brands = app(PublicBrandQuery::class)->listing();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $data = PublicBrandResource::collection($brands)->response()->getData(true);
            self::assertCount(3, $data['data']);
            self::assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    /** @param array<string, mixed> $overrides */
    private function image(array $overrides = []): Media
    {
        return Media::query()->create([...['kind' => 'image', 'disk' => 'public', 'path' => 'media/brand-'.++$this->mediaNumber.'.webp', 'mime_type' => 'image/webp', 'size' => 12, 'title' => 'Brand', 'alt' => 'Логотип бренда'], ...$overrides]);
    }
}
