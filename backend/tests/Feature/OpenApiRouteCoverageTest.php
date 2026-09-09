<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpenApiRouteCoverageTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $specification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->specification = json_decode(
            (string) file_get_contents(base_path('../docs/openapi.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    #[Test]
    public function it_describes_every_registered_v1_http_operation_and_no_other_operation(): void
    {
        self::assertSame($this->registeredOperations(), $this->documentedOperations());
    }

    #[Test]
    public function it_keeps_registered_route_security_and_path_parameters_in_sync(): void
    {
        foreach ($this->v1Routes() as $route) {
            $path = $this->openApiPath($route);

            foreach ($this->routeMethods($route) as $method) {
                $operation = $this->specification['paths'][$path][$method];
                $isAuthenticated = collect($route->gatherMiddleware())
                    ->contains(static fn (string $middleware): bool => $middleware === 'auth:sanctum' || str_ends_with($middleware, 'Authenticate:sanctum'));

                if ($isAuthenticated) {
                    self::assertSame([['sanctumSession' => []]], $operation['security'] ?? null, "$method $path must document Sanctum session authentication.");
                } else {
                    self::assertArrayNotHasKey('security', $operation, "$method $path must not declare administrator authentication.");
                }

                foreach ($route->parameterNames() as $parameter) {
                    $documentedParameter = $this->findParameter($path, $operation, $parameter);
                    self::assertNotNull($documentedParameter, "$method $path must document {{$parameter}}.");
                    self::assertSame('path', $documentedParameter['in']);
                    self::assertTrue($documentedParameter['required']);
                }
            }
        }
    }

    #[Test]
    public function it_requires_complete_operation_contracts_for_responses_errors_pagination_and_examples(): void
    {
        foreach ($this->specification['paths'] as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                self::assertNotEmpty($operation['operationId'] ?? null, "$method $path needs an operationId.");
                self::assertNotEmpty($operation['summary'] ?? null, "$method $path needs a summary.");
                self::assertNotEmpty($operation['responses'] ?? null, "$method $path needs responses.");
                self::assertNotEmpty(array_filter(array_keys($operation['responses']), static fn (string $status): bool => str_starts_with($status, '2')), "$method $path needs a success response.");

                foreach ($operation['responses'] as $status => $response) {
                    $resolvedResponse = $this->resolveResponse($response);
                    self::assertNotEmpty($resolvedResponse['description'] ?? null, "$method $path response $status needs a description.");

                    if ((int) $status >= 400 && isset($resolvedResponse['content']['application/json']['schema'])) {
                        self::assertSame('#/components/schemas/ApiError', $resolvedResponse['content']['application/json']['schema']['$ref'] ?? null, "$method $path error $status must use the standard error envelope.");
                    }
                }

                if (in_array('per_page', array_column($operation['parameters'] ?? [], 'name'), true)) {
                    $responseSchema = $operation['responses']['200']['content']['application/json']['schema']['$ref'] ?? null;
                    self::assertNotNull($responseSchema, "$method $path pagination needs a JSON response schema.");
                    $collection = $this->resolveSchemaReference($responseSchema);
                    self::assertContains('links', $collection['required'] ?? [], "$method $path pagination needs links.");
                    self::assertContains('meta', $collection['required'] ?? [], "$method $path pagination needs meta.");
                }

                if (isset($operation['requestBody'])) {
                    $content = $operation['requestBody']['content'] ?? [];
                    self::assertNotEmpty($content, "$method $path request body needs content.");

                    foreach ($content as $mediaType => $media) {
                        self::assertTrue(
                            isset($media['schema']) || isset($media['example']) || isset($media['examples']),
                            "$method $path request body for $mediaType needs a schema or example.",
                        );
                    }
                }
            }
        }
    }

    /** @return array<int, string> */
    private function registeredOperations(): array
    {
        $operations = [];

        Artisan::call('route:list', ['--path' => 'api/v1', '--json' => true]);
        $routes = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        foreach ($routes as $route) {
            foreach (explode('|', $route['method']) as $method) {
                $method = strtolower($method);

                if ($method !== 'head') {
                    $path = $route['uri'] === 'api/v1'
                        ? '/'
                        : '/'.str_replace('api/v1/', '', $route['uri']);
                    $operations[] = $method.' '.$path;
                }
            }
        }

        sort($operations);

        return $operations;
    }

    /** @return array<int, string> */
    private function documentedOperations(): array
    {
        $operations = [];

        foreach ($this->specification['paths'] as $path => $pathItem) {
            foreach (array_keys($pathItem) as $method) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $operations[] = $method.' '.$path;
                }
            }
        }

        sort($operations);

        return $operations;
    }

    /** @return array<int, Route> */
    private function v1Routes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            static fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1'),
        ));
    }

    /** @return array<int, string> */
    private function routeMethods(Route $route): array
    {
        return array_values(array_filter(array_map(
            static fn (string $method): string => strtolower($method),
            $route->methods(),
        ), static fn (string $method): bool => $method !== 'head'));
    }

    private function openApiPath(Route $route): string
    {
        return $route->uri() === 'api/v1'
            ? '/'
            : '/'.str_replace('api/v1/', '', $route->uri());
    }

    /** @return array<string, mixed>|null */
    private function findParameter(string $path, array $operation, string $name): ?array
    {
        foreach (array_merge($this->specification['paths'][$path]['parameters'] ?? [], $operation['parameters'] ?? []) as $parameter) {
            if (isset($parameter['$ref'])) {
                $parameter = $this->resolveParameterReference($parameter['$ref']);
            }

            if (($parameter['name'] ?? null) === $name) {
                return $parameter;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function resolveParameterReference(string $reference): array
    {
        return $this->specification['components']['parameters'][basename($reference)];
    }

    /** @return array<string, mixed> */
    private function resolveResponse(array $response): array
    {
        return isset($response['$ref'])
            ? $this->specification['components']['responses'][basename($response['$ref'])]
            : $response;
    }

    /** @return array<string, mixed> */
    private function resolveSchemaReference(string $reference): array
    {
        return $this->specification['components']['schemas'][basename($reference)];
    }
}
