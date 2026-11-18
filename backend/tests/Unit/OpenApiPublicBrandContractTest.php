<?php

namespace Tests\Unit;

use Tests\TestCase;

class OpenApiPublicBrandContractTest extends TestCase
{
    public function test_public_brand_profile_and_pagination_are_documented_without_admin_metadata(): void
    {
        $spec = json_decode(file_get_contents(base_path('../docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('#/components/schemas/PublicBrandCollection', $spec['paths']['/brands']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        self::assertArrayHasKey('422', $spec['paths']['/brands']['get']['responses']);
        self::assertArrayHasKey('404', $spec['paths']['/brands/{slug}']['get']['responses']);
        self::assertSame('#/components/schemas/PublicBrandResponse', $spec['paths']['/brands/{slug}']['get']['responses']['200']['content']['application/json']['schema']['$ref']);
        $schema = $spec['components']['schemas']['PublicBrand'];
        self::assertSame(['id', 'name', 'slug', 'description', 'logo_url', 'logo_alt'], array_keys($schema['properties']));
        self::assertSame(array_keys($schema['properties']), $schema['required']);
        self::assertSame(['string', 'null'], $schema['properties']['logo_url']['type']);
        self::assertFalse($schema['additionalProperties']);
    }
}
