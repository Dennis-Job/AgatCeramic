<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_nested_variant_api_is_no_longer_exposed(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'catalog-manager')->sole());
        $product = Product::factory()->create();

        $this->actingAs($user)->getJson("/api/v1/admin/products/{$product->id}/variants")->assertNotFound();
        $this->actingAs($user)->postJson("/api/v1/admin/products/{$product->id}/variants", [])->assertNotFound();
    }
}
