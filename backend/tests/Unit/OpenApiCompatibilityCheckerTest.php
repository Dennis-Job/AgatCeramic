<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgatCeramic\OpenApi\CompatibilityChecker;
use AgatCeramic\OpenApi\CompatibilityPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../scripts/openapi/OpenApiDocument.php';
require_once __DIR__.'/../../scripts/openapi/SchemaCompatibilityChecker.php';
require_once __DIR__.'/../../scripts/openapi/CompatibilityChecker.php';
require_once __DIR__.'/../../scripts/openapi/CompatibilityPolicy.php';

final class OpenApiCompatibilityCheckerTest extends TestCase
{
    #[DataProvider('breakingMutations')]
    public function test_it_detects_recursive_breaking_contract_mutations(callable $mutate, string $expected): void
    {
        $base = $this->fixture();
        $candidate = $base;
        $mutate($candidate);

        $changes = (new CompatibilityChecker($base, $candidate))->breakingChanges();

        self::assertNotEmpty($changes);
        self::assertStringContainsString($expected, implode("\n", $changes));
    }

    /** @return iterable<string, array{callable(array<string, mixed>&): void, string}> */
    public static function breakingMutations(): iterable
    {
        yield 'operation removed' => [static function (array &$spec): void {
            unset($spec['paths']['/things']['post']);
        }, 'operation was removed'];
        yield 'security becomes stricter' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['security'] = [['bearer' => [], 'apiKey' => []]];
        }, 'security requirements became more restrictive'];
        yield 'parameter removed' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['parameters'] = [];
        }, 'parameter query:q was removed'];
        yield 'required parameter added' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['parameters'][] = [
                'name' => 'locale', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string'],
            ];
        }, 'required parameter query:locale was added'];
        yield 'request body becomes required' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['requestBody']['required'] = true;
        }, 'request body became required'];
        yield 'request media type removed' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['requestBody']['content'] = [];
        }, 'media type application/json was removed'];
        yield 'nested request type changes' => [static function (array &$spec): void {
            $spec['components']['schemas']['NestedRequest']['properties']['count']['type'] = 'string';
        }, '.nested.count: type/nullable changed'];
        yield 'request nullable removed' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['properties']['note']['type'] = 'string';
        }, '.note: type/nullable changed'];
        yield 'request enum narrows' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['properties']['state']['enum'] = ['draft'];
        }, 'request enum value "published" was removed'];
        yield 'request lower bound tightens' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['properties']['name']['minLength'] = 3;
        }, 'minLength changed incompatibly'];
        yield 'request format constraint added' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['properties']['name']['format'] = 'email';
        }, 'format changed incompatibly'];
        yield 'request property becomes required' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['required'][] = 'state';
        }, 'property state became required'];
        yield 'response status removed' => [static function (array &$spec): void {
            unset($spec['paths']['/things']['post']['responses']['400']);
        }, 'response status 400 was removed'];
        yield 'response media type removed' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['responses']['200']['content'] = [];
        }, 'media type application/json was removed'];
        yield 'response header removed' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['responses']['200']['headers'] = [];
        }, 'header X-RateLimit was removed'];
        yield 'response required property removed' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingResponse']['required'] = ['id', 'state', 'score'];
        }, 'property note is no longer guaranteed'];
        yield 'response nullable added' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingResponse']['properties']['note']['type'] = ['string', 'null'];
        }, '.note: type/nullable changed'];
        yield 'response enum widens' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingResponse']['properties']['state']['enum'][] = 'archived';
        }, 'response enum value "archived" was added'];
        yield 'response bound widens' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingResponse']['properties']['score']['maximum'] = 200;
        }, 'maximum changed incompatibly'];
        yield 'response format constraint removed' => [static function (array &$spec): void {
            unset($spec['components']['schemas']['ThingResponse']['properties']['note']['format']);
        }, 'format changed incompatibly'];
        yield 'request additional properties become constrained' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['additionalProperties'] = ['type' => 'string'];
        }, 'schema acceptance became incompatible'];
        yield 'request union alternative removed' => [static function (array &$spec): void {
            array_pop($spec['components']['schemas']['ThingRequest']['properties']['choice']['oneOf']);
        }, 'oneOf alternatives changed incompatibly'];
    }

    #[DataProvider('compatibleMutations')]
    public function test_it_accepts_directionally_compatible_mutations(callable $mutate): void
    {
        $base = $this->fixture();
        $candidate = $base;
        $mutate($candidate);

        self::assertSame([], (new CompatibilityChecker($base, $candidate))->breakingChanges());
    }

    /** @return iterable<string, array{callable(array<string, mixed>&): void}> */
    public static function compatibleMutations(): iterable
    {
        yield 'optional request parameter added' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['parameters'][] = [
                'name' => 'locale', 'in' => 'query', 'schema' => ['type' => 'string'],
            ];
        }];
        yield 'security alternative added' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['security'] = [['bearer' => []], ['apiKey' => []]];
        }];
        yield 'request enum widens' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['properties']['state']['enum'][] = 'archived';
        }];
        yield 'request bounds loosen' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['properties']['name']['minLength'] = 1;
            $spec['components']['schemas']['ThingRequest']['properties']['name']['maxLength'] = 60;
        }];
        yield 'optional request property added' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['properties']['locale'] = ['type' => 'string'];
        }];
        yield 'response enum narrows' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingResponse']['properties']['state']['enum'] = ['draft'];
        }];
        yield 'response bounds tighten' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingResponse']['properties']['score']['minimum'] = 1;
            $spec['components']['schemas']['ThingResponse']['properties']['score']['maximum'] = 99;
        }];
        yield 'response nullability narrows' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingResponse']['properties']['nullable_note']['type'] = 'string';
        }];
        yield 'response status added' => [static function (array &$spec): void {
            $spec['paths']['/things']['post']['responses']['409'] = ['description' => 'Conflict'];
        }];
        yield 'operation added' => [static function (array &$spec): void {
            $spec['paths']['/health']['get'] = [
                'operationId' => 'health', 'responses' => ['204' => ['description' => 'Healthy']],
            ];
        }];
        yield 'schema alternatives reordered' => [static function (array &$spec): void {
            $spec['components']['schemas']['ThingRequest']['properties']['choice']['oneOf'] = array_reverse(
                $spec['components']['schemas']['ThingRequest']['properties']['choice']['oneOf'],
            );
        }];
    }

    public function test_minor_or_patch_bumps_cannot_approve_breaking_changes(): void
    {
        $error = (new CompatibilityPolicy)->breakingChangePolicyError(
            'v1.2',
            'v1.3',
            __DIR__.'/../Fixtures/OpenApiCompatibility/migration-plan.md',
        );

        self::assertStringContainsString('cannot approve', (string) $error);
    }

    public function test_adding_authentication_to_a_public_operation_is_breaking(): void
    {
        $base = $this->fixture();
        $base['paths']['/things']['post']['security'] = [];
        $candidate = $base;
        $candidate['paths']['/things']['post']['security'] = [['bearer' => []]];

        self::assertStringContainsString(
            'security requirements became more restrictive',
            implode("\n", (new CompatibilityChecker($base, $candidate))->breakingChanges()),
        );
    }

    public function test_major_bump_requires_a_matching_explicit_migration_plan(): void
    {
        $policy = new CompatibilityPolicy;

        self::assertStringContainsString('requires --migration-plan', (string) $policy->breakingChangePolicyError('v1.2', 'v2.0', null));
        self::assertNull($policy->breakingChangePolicyError(
            'v1.2',
            'v2.0',
            __DIR__.'/../Fixtures/OpenApiCompatibility/migration-plan.md',
        ));
    }

    public function test_repairing_a_previously_dangling_schema_reference_is_compatible(): void
    {
        $base = $this->fixture();
        $candidate = $base;
        unset($base['components']['schemas']['ThingRequest']);

        self::assertSame([], (new CompatibilityChecker($base, $candidate))->breakingChanges());
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode(
            (string) file_get_contents(__DIR__.'/../Fixtures/OpenApiCompatibility/base.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
