<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_manage_brands(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $created = $this->actingAs($actor)->postJson('/api/v1/admin/brands', ['name' => 'Agat Ceramica', 'slug' => 'agat-ceramica', 'description' => 'Ceramic tile manufacturer.', 'country_code' => 'IT', 'is_active' => true])
            ->assertCreated()->assertJsonPath('data.country_code', 'IT');
        $id = $created->json('data.id');

        $this->actingAs($actor)->getJson('/api/v1/admin/brands')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs($actor)->patchJson("/api/v1/admin/brands/{$id}", ['name' => 'Agat Ceramics', 'slug' => 'agat-ceramics', 'country_code' => 'ES', 'is_active' => false])->assertOk()->assertJsonPath('data.country_code', 'ES');
        $this->actingAs($actor)->deleteJson("/api/v1/admin/brands/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('brands', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'brand.created', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'brand.updated', 'entity_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'brand.deleted', 'entity_id' => $id]);
    }

    public function test_brand_validation_and_authorization_are_enforced(): void
    {
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/brands')->assertForbidden();

        $this->actingAs($this->userWithRole('catalog-manager'))->postJson('/api/v1/admin/brands', ['name' => 'Brand', 'slug' => 'Invalid Slug'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')->assertJsonStructure(['error' => ['details' => ['slug']]]);

        $this->actingAs($this->userWithRole('catalog-manager'))->postJson('/api/v1/admin/brands', ['name' => 'Brand', 'slug' => 'brand', 'country_code' => 'Italy'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')->assertJsonStructure(['error' => ['details' => ['country_code']]]);
    }

    public function test_brand_search_filters_before_paginating_and_preserves_search_in_links(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        Brand::factory()->create(['name' => 'A different brand', 'slug' => 'other']);
        for ($number = 1; $number <= 27; $number++) {
            Brand::factory()->create(['name' => sprintf('Ceramic %02d', $number), 'slug' => "ceramic-{$number}"]);
        }

        $firstPage = $this->actingAs($actor)->getJson('/api/v1/admin/brands?search=ceramic')
            ->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 27)
            ->assertJsonPath('data.0.name', 'Ceramic 01')->assertJsonPath('data.24.name', 'Ceramic 25');
        $nextPage = $firstPage->json('links.next');
        $this->assertIsString($nextPage);
        $this->assertStringContainsString('search=ceramic', $nextPage);
        $this->actingAs($actor)->getJson($nextPage)
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 27)->assertJsonPath('data.0.name', 'Ceramic 26');
    }

    public function test_brand_search_matches_name_or_slug_without_case_sensitivity_and_trims_input(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $nameMatch = Brand::factory()->create(['name' => 'Super CERAMIC Tiles', 'slug' => 'tiles']);
        $slugMatch = Brand::factory()->create(['name' => 'Another manufacturer', 'slug' => 'super-ceramic-brand']);
        Brand::factory()->create(['name' => 'Unrelated', 'slug' => 'unrelated']);

        $this->actingAs($actor)->getJson('/api/v1/admin/brands?'.http_build_query(['search' => '  cErAmIc  ']))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $slugMatch->id)->assertJsonPath('data.1.id', $nameMatch->id);
    }

    public function test_brand_search_returns_empty_results_when_no_brand_matches(): void
    {
        Brand::factory()->create(['name' => 'Ceramic', 'slug' => 'ceramic']);

        $this->actingAs($this->userWithRole('catalog-manager'))->getJson('/api/v1/admin/brands?search=missing')
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public function test_postgres_brand_search_matches_cyrillic_names_without_case_sensitivity(): void
    {
        if (Brand::query()->getConnection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL verifies Unicode case conversion; SQLite LOWER only handles ASCII.');
        }

        $brand = Brand::factory()->create(['name' => 'КЕРАМИКА Агат', 'slug' => 'agat']);
        Brand::factory()->create(['name' => 'Другой бренд', 'slug' => 'other']);

        $this->actingAs($this->userWithRole('catalog-manager'))
            ->getJson('/api/v1/admin/brands?'.http_build_query(['search' => 'кЕрАмИкА']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $brand->id);
    }

    public function test_empty_brand_search_keeps_all_brands(): void
    {
        Brand::factory()->count(2)->create();
        $this->actingAs($this->userWithRole('catalog-manager'));

        foreach (['', '   '] as $search) {
            $this->getJson('/api/v1/admin/brands?'.http_build_query(['search' => $search]))
                ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        }
    }

    public function test_brand_search_treats_wildcard_and_escape_characters_as_literal_text(): void
    {
        Brand::factory()->create(['name' => 'Save 100% ceramic', 'slug' => 'discount']);
        Brand::factory()->create(['name' => 'Under_score', 'slug' => 'underscore']);
        Brand::factory()->create(['name' => 'Bang! brand', 'slug' => 'bang']);
        Brand::factory()->create(['name' => 'Unrelated brand', 'slug' => 'unrelated']);
        $this->actingAs($this->userWithRole('catalog-manager'));

        foreach (['%' => 'Save 100% ceramic', '_' => 'Under_score', '!' => 'Bang! brand'] as $search => $name) {
            $this->getJson('/api/v1/admin/brands?'.http_build_query(['search' => $search]))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', $name);
        }
    }

    public function test_brand_search_validates_input_and_requires_catalog_permission(): void
    {
        $this->getJson('/api/v1/admin/brands?search=ceramic')->assertUnauthorized();
        $this->actingAs($this->userWithRole('analyst'))->getJson('/api/v1/admin/brands?search=ceramic')->assertForbidden();
        $this->actingAs($this->userWithRole('catalog-manager'));

        foreach ([str_repeat('a', 256), ['ceramic']] as $search) {
            $this->getJson('/api/v1/admin/brands?'.http_build_query(['search' => $search]))
                ->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed')
                ->assertJsonStructure(['error' => ['details' => ['search']]]);
        }

        foreach (['0', '-1', 'wrong'] as $page) {
            $this->getJson('/api/v1/admin/brands?'.http_build_query(['page' => $page]))
                ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['page']]]);
        }
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
