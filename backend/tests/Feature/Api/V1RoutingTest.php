<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class V1RoutingTest extends TestCase
{
    public function test_api_v1_entry_point_is_available(): void
    {
        $this->getJson('/api/v1')
            ->assertOk()
            ->assertExactJson([
                'version' => 'v1',
            ]);
    }

    public function test_api_v1_routes_are_named_under_the_version_namespace(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.index');

        $this->assertNotNull($route);
        $this->assertSame('api/v1', $route->uri());
    }

    public function test_unversioned_api_entry_point_is_not_exposed(): void
    {
        $this->getJson('/api')->assertNotFound();
    }
}
