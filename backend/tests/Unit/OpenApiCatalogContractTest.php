<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenApiCatalogContractTest extends TestCase
{
    #[Test]
    public function it_documents_every_catalog_management_route_and_resource_schema(): void
    {
        $spec = json_decode(file_get_contents(base_path('../docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR);

        $expectedOperations = [
            '/admin/categories' => ['get', 'post'],
            '/admin/categories/{category}' => ['get', 'put', 'patch', 'delete'],
            '/admin/categories/tree' => ['get'],
            '/admin/categories/{category}/attributes' => ['get', 'put'],
            '/admin/categories/{category}/attribute-groups' => ['get', 'put'],
            '/admin/attribute-groups' => ['get', 'post'],
            '/admin/attribute-groups/{attribute_group}' => ['get', 'put', 'patch', 'delete'],
            '/admin/attributes' => ['get', 'post'],
            '/admin/attributes/{attribute}' => ['get', 'put', 'patch', 'delete'],
            '/admin/brands' => ['get', 'post'],
            '/admin/brands/{brand}' => ['get', 'put', 'patch', 'delete'],
            '/admin/products' => ['get', 'post'],
            '/admin/products/{product}' => ['get', 'put', 'patch', 'delete'],
            '/admin/products/{product}/variants' => ['get', 'post'],
            '/admin/products/{product}/variants/{variant}' => ['get', 'put', 'patch', 'delete'],
            '/admin/products/{product}/attributes' => ['get', 'put'],
            '/admin/products/{product}/images' => ['get', 'post'],
            '/admin/products/{product}/images/{image}' => ['patch', 'delete'],
            '/admin/products/{product}/relations' => ['get', 'put'],
        ];

        foreach ($expectedOperations as $path => $methods) {
            self::assertArrayHasKey($path, $spec['paths']);

            foreach ($methods as $method) {
                self::assertArrayHasKey($method, $spec['paths'][$path]);
            }
        }

        foreach (['Category', 'AttributeGroup', 'Attribute', 'AttributeOption', 'Brand', 'Product', 'ProductVariant', 'ProductVariantAttributeValue', 'ProductAttributeValue', 'ProductImage', 'ProductRelation'] as $schema) {
            self::assertArrayHasKey($schema, $spec['components']['schemas']);
            self::assertArrayHasKey($schema.'Response', $spec['components']['schemas']);
            self::assertArrayHasKey($schema.'Collection', $spec['components']['schemas']);
        }

        self::assertSame('multipart/form-data', array_key_first($spec['paths']['/admin/products/{product}/images']['post']['requestBody']['content']));
        self::assertContains('links', $spec['components']['schemas']['ProductVariantCollection']['required']);
        self::assertContains('meta', $spec['components']['schemas']['ProductImageCollection']['required']);
        self::assertArrayNotHasKey('required', $spec['components']['schemas']['UpdateProductVariantRequest']);
        self::assertSame(['options'], $spec['components']['schemas']['StoreAttributeRequest']['allOf'][0]['then']['required']);
        self::assertSame(['options'], $spec['components']['schemas']['UpdateAttributeRequest']['allOf'][0]['then']['required']);
        self::assertArrayHasKey('422', $spec['paths']['/admin/attributes/{attribute}']['delete']['responses']);
        self::assertNotEmpty($spec['paths']['/admin/attributes/{attribute}']['patch']['description']);
        self::assertSame(
            $spec['paths']['/admin/attributes/{attribute}']['patch']['description'],
            $spec['paths']['/admin/attributes/{attribute}']['put']['description'],
        );
    }
}
