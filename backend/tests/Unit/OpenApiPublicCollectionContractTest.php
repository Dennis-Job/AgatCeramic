<?php

namespace Tests\Unit;

use Tests\TestCase;

class OpenApiPublicCollectionContractTest extends TestCase
{
    public function test_public_collection_profile_catalog_scope_and_product_links_are_documented(): void
    {
        $spec = json_decode(file_get_contents(base_path('../docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $listing = $spec['paths']['/collections']['get']['responses'];
        $profile = $spec['paths']['/brands/{brand}/collections/{slug}']['get']['responses'];
        self::assertSame('#/components/schemas/PublicCollectionPageListing', $listing['200']['content']['application/json']['schema']['$ref']);
        self::assertArrayHasKey('422', $listing);
        self::assertSame('#/components/schemas/PublicCollectionPageResponse', $profile['200']['content']['application/json']['schema']['$ref']);
        self::assertArrayHasKey('404', $profile);
        $schema = $spec['components']['schemas']['PublicCollectionPage'];
        self::assertEqualsCanonicalizing(['id', 'name', 'slug', 'brand_slug', 'brand_name', 'headline', 'description', 'country_code', 'image_url', 'image_alt'], array_keys($schema['properties']));
        self::assertEqualsCanonicalizing(array_keys($schema['properties']), $schema['required']);
        self::assertFalse($schema['additionalProperties']);
        self::assertContains('collection_slug', array_column($spec['paths']['/catalog']['get']['parameters'], 'name'));
        $brand = $spec['components']['schemas']['PublicProductDetail']['properties']['brand']['anyOf'][0];
        self::assertSame('boolean', $brand['properties']['page_available']['type']);
        self::assertNotContains('page_available', $brand['required']);
    }
}
