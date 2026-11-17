<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandCatalogNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_existing_brands_with_default_off_and_is_reversible(): void
    {
        $brand = Brand::factory()->create(['show_in_catalog_menu' => true]);
        $migration = require database_path('migrations/2026_11_17_000001_add_show_in_catalog_menu_to_brands.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('brands', 'show_in_catalog_menu'));
        $migration->up();
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => $brand->name, 'show_in_catalog_menu' => false]);
        $this->assertFalse($brand->refresh()->show_in_catalog_menu);
    }

    public function test_brand_menu_defaults_off_and_can_be_created_enabled(): void
    {
        $this->actingAs($this->userWithRole('catalog-manager'));
        $id = $this->postJson('/api/v1/admin/brands', ['name' => 'Default', 'slug' => 'default'])
            ->assertCreated()->assertJsonPath('data.show_in_catalog_menu', false)->json('data.id');
        $this->assertDatabaseHas('brands', ['id' => $id, 'show_in_catalog_menu' => false]);
        $enabled = $this->postJson('/api/v1/admin/brands', ['name' => 'Enabled', 'slug' => 'enabled', 'show_in_catalog_menu' => true])
            ->assertCreated()->assertJsonPath('data.show_in_catalog_menu', true)->json('data.id');
        $this->getJson("/api/v1/admin/brands/{$enabled}")->assertOk()->assertJsonPath('data.show_in_catalog_menu', true);
        $this->getJson('/api/v1/admin/brands?search=enabled')->assertOk()->assertJsonPath('data.0.show_in_catalog_menu', true);
    }

    public function test_partial_update_preserves_menu_setting_and_records_explicit_changes(): void
    {
        $brand = Brand::factory()->create();
        $this->actingAs($this->userWithRole('catalog-manager'));
        $this->patchJson("/api/v1/admin/brands/{$brand->id}", ['show_in_catalog_menu' => true])
            ->assertOk()->assertJsonPath('data.show_in_catalog_menu', true);
        $log = AuditLog::query()->where('action', 'brand.updated')->sole();
        $this->assertSame(['before' => false, 'after' => true], data_get($log->metadata, 'changes.show_in_catalog_menu'));
        $this->patchJson("/api/v1/admin/brands/{$brand->id}", ['description' => 'Changed description'])
            ->assertOk()->assertJsonPath('data.show_in_catalog_menu', true);
        $this->patchJson("/api/v1/admin/brands/{$brand->id}", ['show_in_catalog_menu' => false])
            ->assertOk()->assertJsonPath('data.show_in_catalog_menu', false);
        $this->assertFalse($brand->refresh()->show_in_catalog_menu);
    }

    public function test_menu_boolean_is_validated_for_create_and_partial_update(): void
    {
        $brand = Brand::factory()->create();
        $this->actingAs($this->userWithRole('catalog-manager'));
        foreach ([null, 'yes', [], 2] as $invalid) {
            $this->postJson('/api/v1/admin/brands', ['name' => 'Invalid', 'slug' => 'invalid', 'show_in_catalog_menu' => $invalid])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['show_in_catalog_menu']]]);
            $this->patchJson("/api/v1/admin/brands/{$brand->id}", ['show_in_catalog_menu' => $invalid])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['show_in_catalog_menu']]]);
        }
    }

    public function test_menu_changes_require_existing_catalog_management_permission(): void
    {
        $brand = Brand::factory()->create();
        $payload = ['name' => 'Denied', 'slug' => 'denied', 'show_in_catalog_menu' => true];
        $this->postJson('/api/v1/admin/brands', $payload)->assertUnauthorized();
        $this->patchJson("/api/v1/admin/brands/{$brand->id}", $payload)->assertUnauthorized();
        $this->actingAs($this->userWithRole('analyst'));
        $this->postJson('/api/v1/admin/brands', $payload)->assertForbidden();
        $this->patchJson("/api/v1/admin/brands/{$brand->id}", $payload)->assertForbidden();
        $this->assertFalse($brand->refresh()->show_in_catalog_menu);
    }

    public function test_public_navigation_is_global_and_contains_only_active_opted_in_brands(): void
    {
        $category = Category::factory()->create();
        $selected = Brand::factory()->create(['name' => 'Alpha', 'slug' => 'alpha', 'show_in_catalog_menu' => true]);
        $empty = Brand::factory()->create(['name' => 'Beta without products', 'slug' => 'beta', 'show_in_catalog_menu' => true]);
        $ordinary = Brand::factory()->create(['name' => 'Ordinary', 'slug' => 'ordinary']);
        Brand::factory()->create(['is_active' => false, 'show_in_catalog_menu' => true]);
        Product::factory()->count(25)->create(['category_id' => $category->id, 'brand_id' => $ordinary->id]);
        $expected = [
            ['id' => $selected->id, 'name' => 'Alpha', 'slug' => 'alpha'],
            ['id' => $empty->id, 'name' => 'Beta without products', 'slug' => 'beta'],
        ];
        foreach ([[], ['category' => $category->slug, 'page' => 2, 'include_filters' => 1], ['category' => 'missing', 'brand' => 'ordinary', 'in_stock' => 1, 'price_from' => '999999']] as $filters) {
            $this->getJson('/api/v1/catalog?'.http_build_query($filters))->assertOk()->assertJsonPath('brand_navigation', $expected);
        }
        $this->getJson('/api/v1/catalog?include_filters=1&category='.$category->slug)
            ->assertOk()->assertJsonPath('filters.brands.0.id', $ordinary->id)->assertJsonCount(1, 'filters.brands');
        $this->getJson('/api/v1/catalog?brand=beta')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('brand_navigation', $expected);
        $selected->update(['show_in_catalog_menu' => false]);
        $empty->update(['is_active' => false]);
        $this->getJson('/api/v1/catalog')->assertOk()->assertJsonPath('brand_navigation', []);
    }

    private function userWithRole(string $slug): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
