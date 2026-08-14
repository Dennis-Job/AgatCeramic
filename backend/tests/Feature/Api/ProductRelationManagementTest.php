<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductRelation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRelationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_manager_can_replace_product_relations(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();
        $related = Product::factory()->create(['name' => 'Related product']);
        $recommended = Product::factory()->create(['name' => 'Recommended product']);

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}/relations", [
            'relations' => [
                ['related_product_id' => $recommended->id, 'type' => 'recommended', 'sort_order' => 20],
                ['related_product_id' => $related->id, 'type' => 'related', 'sort_order' => 10],
            ],
        ])->assertOk()->assertJsonPath('data.0.related_product_id', $related->id)
            ->assertJsonPath('data.0.related_product.name', 'Related product');

        $this->actingAs($actor)->getJson("/api/v1/admin/products/{$product->id}/relations")
            ->assertOk()->assertJsonPath('data.1.related_product_id', $recommended->id);

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}/relations", [
            'relations' => [['related_product_id' => $recommended->id, 'type' => 'related']],
        ])->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseMissing('product_relations', ['product_id' => $product->id, 'related_product_id' => $related->id]);
        $this->assertDatabaseHas('product_relations', ['product_id' => $product->id, 'related_product_id' => $recommended->id, 'type' => 'related', 'sort_order' => 0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.relations-updated', 'entity_id' => $product->id]);
    }

    public function test_product_relations_must_not_be_self_referential_or_reverse_duplicates(): void
    {
        $actor = $this->userWithRole('catalog-manager');
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        ProductRelation::query()->create(['product_id' => $other->id, 'related_product_id' => $product->id, 'type' => 'related']);

        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}/relations", [
            'relations' => [['related_product_id' => $product->id, 'type' => 'related']],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['relations.0.related_product_id']]]);
        $this->actingAs($actor)->putJson("/api/v1/admin/products/{$product->id}/relations", [
            'relations' => [['related_product_id' => $other->id, 'type' => 'recommended']],
        ])->assertUnprocessable()->assertJsonStructure(['error' => ['details' => ['relations.0.related_product_id']]]);
        $this->actingAs($this->userWithRole('analyst'))->getJson("/api/v1/admin/products/{$product->id}/relations")->assertForbidden();
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
