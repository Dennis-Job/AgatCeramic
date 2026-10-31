<?php

namespace Tests\Feature\Api;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductManagementService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCollectionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_manage_filter_and_audit_collections(): void
    {
        $this->actingAs($this->actor());
        $brand = Brand::factory()->create();
        $id = $this->postJson('/api/v1/admin/collections', ['brand_id' => $brand->id, 'name' => 'Аллея', 'slug' => 'alleya', 'headline' => 'История', 'description' => 'Описание', 'is_active' => true])
            ->assertCreated()->assertJsonPath('data.brand.id', $brand->id)->assertJsonPath('data.products_count', 0)->json('data.id');
        $this->getJson('/api/v1/admin/collections?'.http_build_query(['brand_id' => $brand->id, 'search' => 'alleya', 'is_active' => 1]))->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/v1/admin/collections/{$id}", ['headline' => null, 'is_active' => false])->assertOk()->assertJsonPath('data.headline', null);
        $this->getJson("/api/v1/admin/collections/{$id}")->assertOk()->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/v1/admin/collections/{$id}")->assertNoContent();
        foreach (['created', 'updated', 'deleted'] as $event) {
            $this->assertDatabaseHas('audit_logs', ['action' => 'collection.'.$event, 'entity_id' => $id]);
        }
    }

    public function test_permission_name_uniqueness_and_public_image_are_validated(): void
    {
        $this->actingAs($this->actor('analyst'))->getJson('/api/v1/admin/collections')->assertForbidden();
        $this->postJson('/api/v1/admin/collections', [])->assertForbidden();
        $this->actingAs($this->actor());
        $brand = Brand::factory()->create();
        $payload = ['brand_id' => $brand->id, 'name' => 'Аллея', 'slug' => 'alleya'];
        $this->postJson('/api/v1/admin/collections', $payload)->assertCreated();
        $this->postJson('/api/v1/admin/collections', [...$payload, 'name' => '  АЛЛЕЯ  ', 'slug' => 'other'])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['name']]]);
        $this->postJson('/api/v1/admin/collections', [...$payload, 'brand_id' => Brand::factory()->create()->id])->assertCreated();
        $media = Media::query()->create(['kind' => 'image', 'disk' => 'local', 'path' => 'private.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'title' => 'Private']);
        $this->postJson('/api/v1/admin/collections', [...$payload, 'name' => 'Other', 'slug' => 'other', 'image_id' => $media->id])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['image_id']]]);
    }

    public function test_assignment_requires_matching_brand_enabled_category_and_active_collection(): void
    {
        $this->actingAs($this->actor());
        $collection = ProductCollection::factory()->create();
        $category = Category::factory()->create(['uses_collections' => true]);
        $product = Product::factory()->create(['brand_id' => $collection->brand_id, 'category_id' => $category->id]);
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['collection_id' => $collection->id])->assertOk()->assertJsonPath('data.collection_id', $collection->id);
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['brand_id' => Brand::factory()->create()->id])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['category_id' => Category::factory()->create()->id])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/categories/'.$category->id, ['uses_collections' => false])->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/collections/'.$collection->id)->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/brands/'.$collection->brand_id)->assertUnprocessable();
        $collection->update(['is_active' => false]);
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['description' => 'Still assigned'])->assertOk();
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['collection_id' => null])->assertOk()->assertJsonPath('data.collection_id', null);
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['collection_id' => $collection->id])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/categories/'.$category->id, ['uses_collections' => false])->assertOk();
    }

    public function test_legacy_attribute_is_archived_and_cannot_be_written_or_required(): void
    {
        $this->actingAs($this->actor());
        $product = Product::factory()->create(['is_active' => false]);
        $legacy = Attribute::query()->create(['name' => 'Коллекция', 'slug' => 'kollektsiya', 'type' => 'string']);
        $product->category->attributes()->attach($legacy, ['is_required' => true]);
        $product->attributeValues()->create(['attribute_id' => $legacy->id, 'value' => 'История']);
        $url = '/api/v1/admin/products/'.$product->id.'/attributes';
        $this->putJson($url, ['attributes' => []])->assertOk();
        $this->assertDatabaseHas('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $legacy->id]);
        $this->putJson($url, ['attributes' => [['attribute_id' => $legacy->id, 'value' => 'New']]])->assertUnprocessable();
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['is_active' => true])->assertOk();
        $this->putJson($url, ['attributes' => []])->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['category_id' => Category::factory()->create()->id])->assertOk();
    }

    public function test_all_valid_false_boolean_forms_preserve_collection_category_integrity(): void
    {
        $this->actingAs($this->actor());
        $collection = ProductCollection::factory()->create();
        $category = Category::factory()->create(['uses_collections' => true]);
        Product::factory()->create(['category_id' => $category->id, 'brand_id' => $collection->brand_id, 'collection_id' => $collection->id]);
        foreach ([false, 0, '0'] as $value) {
            $this->patchJson('/api/v1/admin/categories/'.$category->id, ['uses_collections' => $value])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['uses_collections']]]);
            $this->assertTrue($category->refresh()->uses_collections);
        }
    }

    public function test_legacy_values_do_not_have_to_match_between_group_members_and_cannot_be_axes(): void
    {
        $this->actingAs($this->actor());
        $category = Category::factory()->create();
        $legacy = Attribute::factory()->create(['slug' => 'kollektsiya', 'type' => 'string']);
        $color = Attribute::factory()->create(['type' => 'string']);
        $category->attributes()->attach([$legacy->id, $color->id]);
        $products = Product::factory()->count(2)->create(['category_id' => $category->id, 'brand_id' => null]);
        foreach ($products as $i => $product) {
            $product->attributeValues()->createMany([
                ['attribute_id' => $legacy->id, 'value' => 'History '.$i],
                ['attribute_id' => $color->id, 'value' => 'Color '.$i],
            ]);
        }
        $payload = ['name' => 'Colors', 'code' => 'colors', 'product_ids' => $products->modelKeys(), 'axis_attribute_ids' => [$color->id]];
        $this->postJson('/api/v1/admin/product-groups', $payload)->assertCreated();
        $this->putJson('/api/v1/admin/products/'.$products[0]->id.'/attributes', ['attributes' => [['attribute_id' => $color->id, 'value' => 'Another']]])->assertOk();
        $this->assertSame('History 1', $products[1]->attributeValues()->where('attribute_id', $legacy->id)->sole()->value);
        $this->postJson('/api/v1/admin/product-groups', [...$payload, 'code' => 'reserved', 'axis_attribute_ids' => [$legacy->id]])->assertUnprocessable();
    }

    public function test_application_boundary_rejects_invalid_import_assignment_and_category_does_not_inherit(): void
    {
        $actor = $this->actor();
        $collection = ProductCollection::factory()->create();
        $parent = Category::factory()->create(['uses_collections' => true]);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $this->assertFalse($child->refresh()->uses_collections);
        try {
            app(ProductManagementService::class)->create($actor, [
                'name' => 'Imported', 'slug' => 'imported', 'category_id' => $child->id,
                'brand_id' => $collection->brand_id, 'collection_id' => $collection->id,
                'unit' => 'piece', 'price' => 1, 'stock_quantity' => 0, 'is_active' => false,
            ]);
            $this->fail('Every application caller must validate collection assignment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('collection_id', $exception->errors());
        }
        $this->assertDatabaseMissing('products', ['slug' => 'imported']);
    }

    public function test_product_search_uses_canonical_collection_and_ignores_stale_archival_values(): void
    {
        $this->actingAs($this->actor());
        $collection = ProductCollection::factory()->create(['name' => 'Canonical collection']);
        $product = Product::factory()->create(['brand_id' => $collection->brand_id, 'collection_id' => $collection->id]);
        $other = Product::factory()->create();
        $legacy = Attribute::factory()->create(['slug' => 'kollektsiya', 'type' => 'string']);
        $other->attributeValues()->create(['attribute_id' => $legacy->id, 'value' => 'Canonical collection']);
        $this->getJson('/api/v1/admin/products?search=Canonical')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
    }

    public function test_collection_media_is_protected_while_referenced_and_cleaned_after_detach(): void
    {
        $this->actingAs($this->actor());
        $image = Media::query()->create(['kind' => 'image', 'disk' => 'public', 'path' => 'collection.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'title' => 'Collection']);
        $collection = ProductCollection::factory()->create(['image_id' => $image->id]);
        $this->deleteJson('/api/v1/admin/media/'.$image->id)->assertUnprocessable();
        $this->patchJson('/api/v1/admin/collections/'.$collection->id, ['image_id' => null])->assertOk();
        $this->assertDatabaseMissing('media', ['id' => $image->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.updated', 'entity_id' => $collection->id]);
    }

    private function actor(string $role = 'catalog-manager'): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::query()->where('slug', $role)->sole());

        return $actor;
    }
}
