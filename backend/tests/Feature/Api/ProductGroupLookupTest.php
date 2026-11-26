<?php

namespace Tests\Feature\Api;

use App\Models\ProductGroup;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductGroupLookupTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/v1/admin/product-groups/lookup';

    public function test_lookup_returns_a_bounded_ordered_list_of_summaries(): void
    {
        $first = ProductGroup::factory()->create(['name' => 'Alpha', 'code' => 'Z-FIRST']);
        $second = ProductGroup::factory()->create(['name' => 'Alpha', 'code' => 'A-SECOND']);
        ProductGroup::factory()->count(24)->create(['name' => 'Middle']);
        $last = ProductGroup::factory()->create(['name' => 'Omega', 'code' => 'MATCH-LAST']);

        $this->signInAsCatalogManager();

        $response = $this->getJson(self::PATH.'?search=%20')
            ->assertOk()
            ->assertJsonCount(25, 'data');
        self::assertSame(['data'], array_keys($response->json()));
        self::assertSame([
            ['id' => $first->id, 'name' => 'Alpha', 'code' => 'Z-FIRST'],
            ['id' => $second->id, 'name' => 'Alpha', 'code' => 'A-SECOND'],
        ], array_slice($response->json('data'), 0, 2));
        foreach ($response->json('data') as $group) {
            self::assertSame(['id', 'name', 'code'], array_keys($group));
        }

        $this->getJson(self::PATH.'?search=%20match-last%20')->assertOk()->assertExactJson([
            'data' => [['id' => $last->id, 'name' => 'Omega', 'code' => 'MATCH-LAST']],
        ]);
    }

    public function test_name_search_treats_like_metacharacters_as_literal(): void
    {
        $match = ProductGroup::factory()->create(['name' => 'STOCK 25%_!']);
        ProductGroup::factory()->create(['name' => 'STOCK 250X!']);

        $this->signInAsCatalogManager();

        $this->getJson(self::PATH.'?search='.rawurlencode('stock 25%_!'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->getJson(self::PATH.'?search=unmatched')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_lookup_requires_catalog_permission_and_validates_the_query(): void
    {
        $this->getJson(self::PATH)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(self::PATH)->assertForbidden();

        $this->signInAsCatalogManager();

        $this->getJson(self::PATH.'?search[]=alpha')->assertUnprocessable()->assertJsonValidationErrors('search', 'error.details');
        $this->getJson(self::PATH.'?search='.str_repeat('x', 256))->assertUnprocessable()->assertJsonValidationErrors('search', 'error.details');
    }

    private function signInAsCatalogManager(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'catalog-manager')->sole());
        $this->actingAs($user);
    }
}
