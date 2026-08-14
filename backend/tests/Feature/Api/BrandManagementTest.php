<?php

namespace Tests\Feature\Api;

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

    private function userWithRole(string $slug): User
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $slug)->sole());

        return $user;
    }
}
