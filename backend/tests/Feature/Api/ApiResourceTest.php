<?php

namespace Tests\Feature\Api;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiResourceTest extends TestCase
{
    public function test_resource_uses_the_stable_data_envelope(): void
    {
        $resource = $this->resourceFor([
            'id' => 17,
            'name' => 'Керамическая плитка',
        ]);

        $this->assertSame([
            'data' => [
                'id' => 17,
                'name' => 'Керамическая плитка',
            ],
        ], $resource->response($this->app['request'])->getData(true));
    }

    public function test_resource_collection_uses_the_stable_data_envelope(): void
    {
        $resource = $this->resourceFor([
            'id' => 17,
            'name' => 'Керамическая плитка',
        ]);
        $resourceClass = $resource::class;

        $collection = $resourceClass::collection([
            ['id' => 17, 'name' => 'Керамическая плитка'],
            ['id' => 18, 'name' => 'Керамогранит'],
        ]);

        $this->assertSame([
            'data' => [
                ['id' => 17, 'name' => 'Керамическая плитка'],
                ['id' => 18, 'name' => 'Керамогранит'],
            ],
        ], $collection->response($this->app['request'])->getData(true));
    }

    /**
     * @param  array{id: int, name: string}  $resource
     */
    private function resourceFor(array $resource): ApiResource
    {
        return new class($resource) extends ApiResource
        {
            /**
             * @return array{id: int, name: string}
             */
            public function toArray(Request $request): array
            {
                return [
                    'id' => $this->resource['id'],
                    'name' => $this->resource['name'],
                ];
            }
        };
    }
}
