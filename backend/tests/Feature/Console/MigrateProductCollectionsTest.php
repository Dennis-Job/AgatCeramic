<?php

namespace Tests\Feature\Console;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductCollection;
use App\Models\Role;
use App\Models\User;
use App\Services\LegacyCollectionMigrationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrateProductCollectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_is_read_only_and_reports_missing_brand_and_case_ambiguity(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $this->legacy($category, $brand, 'Аллея');
        $this->legacy($category, $brand, 'АЛЛЕЯ');
        $this->legacy($category, null, 'Без бренда');
        $this->legacy($category, $brand, ['unexpected']);

        $report = app(LegacyCollectionMigrationService::class)->scan();

        $this->assertSame(4, $report['checked']);
        $this->assertCount(4, $report['issues']);
        $this->assertFalse($report['ready_for_cutover']);
        $this->assertDatabaseCount('product_collections', 0);
        $this->assertFalse($category->refresh()->uses_collections);
        $this->assertDatabaseCount('product_attribute_values', 4);
    }

    public function test_apply_scopes_exact_names_to_brand_preserves_history_and_is_idempotent(): void
    {
        $category = Category::factory()->create();
        $firstBrand = Brand::factory()->create();
        $secondBrand = Brand::factory()->create();
        $first = $this->legacy($category, $firstBrand, 'Аллея');
        $second = $this->legacy($category, $firstBrand, 'Аллея');
        $third = $this->legacy($category, $secondBrand, 'Аллея');
        $service = app(LegacyCollectionMigrationService::class);
        $actor = $this->actor();

        $before = $service->scan();
        $this->assertSame([$category->id], $before['suggested_category_ids']);
        $this->assertDatabaseCount('product_collections', 0);
        $report = $service->apply($actor, [$category->id]);

        $this->assertSame(3, $report['assigned']);
        $this->assertTrue($report['ready_for_cutover']);
        $this->assertNotNull($first->refresh()->collection_id);
        $this->assertSame($first->collection_id, $second->refresh()->collection_id);
        $this->assertNotSame($first->collection_id, $third->refresh()->collection_id);
        $this->assertDatabaseCount('product_collections', 2);
        $this->assertDatabaseCount('product_attribute_values', 3);
        $this->assertSame(0, $service->apply($actor, [])['assigned']);
        $this->assertDatabaseCount('product_collections', 2);
        $this->assertNull(ProductCollection::query()->firstOrFail()->image_id);
    }

    public function test_apply_does_not_enable_categories_without_explicit_selection_or_overwrite_assignments(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $product = $this->legacy($category, $brand, 'Аллея');
        $service = app(LegacyCollectionMigrationService::class);
        $actor = $this->actor();

        $report = $service->apply($actor, []);
        $this->assertSame(0, $report['assigned']);
        $this->assertNull($product->refresh()->collection_id);
        $this->assertFalse($category->refresh()->uses_collections);

        $category->update(['uses_collections' => true]);
        $other = ProductCollection::query()->create(['brand_id' => $brand->id, 'name' => 'Другая', 'slug' => 'other', 'is_active' => true]);
        $product->update(['collection_id' => $other->id]);
        $report = $service->apply($actor, []);
        $this->assertSame(0, $report['assigned']);
        $this->assertSame($other->id, $product->refresh()->collection_id);
        $this->assertContains('assignment_conflict', array_column($report['issues'], 'reason'));
    }

    public function test_command_requires_explicit_backup_and_authorized_actor_for_apply(): void
    {
        $this->artisan('catalog:collections-migrate')->assertSuccessful();
        $this->artisan('catalog:collections-migrate', ['--apply' => true])->assertFailed();
        $user = User::factory()->create();
        $this->artisan('catalog:collections-migrate', ['--apply' => true, '--backup-verified' => true, '--actor' => $user->id])->assertFailed();
        $this->assertDatabaseCount('product_collections', 0);
    }

    public function test_valid_long_names_survive_transliteration_without_overflowing_slug(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $this->legacy($category, $brand, str_repeat('a', 247));
        $this->legacy($category, $brand, str_repeat('щ', 150));
        $report = app(LegacyCollectionMigrationService::class)->apply($this->actor(), [$category->id]);
        $this->assertSame(2, $report['assigned']);
        $this->assertTrue($report['ready_for_cutover']);
        foreach (ProductCollection::query()->get() as $collection) {
            $this->assertLessThanOrEqual(255, strlen($collection->slug));
        }
    }

    private function legacy(Category $category, ?Brand $brand, mixed $value): Product
    {
        $attribute = Attribute::query()->firstOrCreate(['slug' => 'kollektsiya'], ['name' => 'Коллекция', 'type' => 'string']);
        $category->attributes()->syncWithoutDetaching([$attribute->id]);
        $product = Product::factory()->create(['category_id' => $category->id, 'brand_id' => $brand?->id, 'is_active' => false]);
        ProductAttributeValue::query()->create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => $value]);

        return $product;
    }

    private function actor(): User
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'catalog-manager')->sole());

        return $user;
    }
}
