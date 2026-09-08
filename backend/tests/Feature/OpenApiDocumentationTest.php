<?php

namespace Tests\Feature;

use JsonException;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_openapi_specification_describes_the_implemented_v1_entry_point(): void
    {
        $specification = json_decode(
            (string) file_get_contents(base_path('../docs/openapi.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('3.1.1', $specification['openapi']);
        $this->assertSame('/api/v1', $specification['servers'][0]['url']);
        $this->assertArrayHasKey('/', $specification['paths']);
        $this->assertArrayHasKey('get', $specification['paths']['/']);
        $this->assertSame('getApiVersion', $specification['paths']['/']['get']['operationId']);
        $this->assertSame(
            '#/components/schemas/VersionResponse',
            $specification['paths']['/']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertArrayHasKey('ApiError', $specification['components']['schemas']);
        $this->assertSame(
            'resolveGuestCart',
            $specification['paths']['/cart']['get']['operationId'],
        );
        $this->assertSame(
            '#/components/schemas/CartResponse',
            $specification['paths']['/cart']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            'addGuestCartItem',
            $specification['paths']['/cart/items']['post']['operationId'],
        );
        $this->assertSame(
            'removeGuestCartItem',
            $specification['paths']['/cart/items/{item}']['delete']['operationId'],
        );
        $this->assertArrayHasKey('CartItem', $specification['components']['schemas']);
        $this->assertArrayHasKey('/admin/categories/{category}/attributes', $specification['paths']);
        $this->assertSame(
            'replaceCategoryAttributes',
            $specification['paths']['/admin/categories/{category}/attributes']['put']['operationId'],
        );
        $this->assertArrayHasKey('ReplaceCategoryAttributesRequest', $specification['components']['schemas']);
    }
}
