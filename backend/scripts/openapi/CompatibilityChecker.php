<?php

declare(strict_types=1);

namespace AgatCeramic\OpenApi;

final class CompatibilityChecker
{
    private const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];

    /** @var array<int, string> */
    private array $changes = [];

    private readonly OpenApiDocument $baseDocument;

    private readonly OpenApiDocument $candidateDocument;

    private readonly SchemaCompatibilityChecker $schemas;

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    public function __construct(
        private readonly array $base,
        private readonly array $candidate,
    ) {
        $this->baseDocument = new OpenApiDocument($base);
        $this->candidateDocument = new OpenApiDocument($candidate);
        $this->schemas = new SchemaCompatibilityChecker($this->baseDocument, $this->candidateDocument);
    }

    /** @return array<int, string> */
    public function breakingChanges(): array
    {
        $this->changes = [];
        $candidateOperations = $this->operations($this->candidate);

        foreach ($this->operations($this->base) as $key => $baseOperation) {
            $candidateOperation = $candidateOperations[$key] ?? null;
            if ($candidateOperation === null) {
                $this->changes[] = "{$key}: operation was removed.";

                continue;
            }

            [$path, $method] = explode(' ', $key, 2);
            $this->compareOperation($path, $baseOperation, $candidateOperation, strtoupper($method).' '.$path);
        }

        return array_values(array_unique($this->changes));
    }

    /** @param array<string, mixed> $specification @return array<string, array<string, mixed>> */
    private function operations(array $specification): array
    {
        $operations = [];

        foreach (($specification['paths'] ?? []) as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }
            foreach (self::METHODS as $method) {
                if (isset($pathItem[$method]) && is_array($pathItem[$method])) {
                    $operations[$path.' '.$method] = $pathItem[$method];
                }
            }
        }

        return $operations;
    }

    /** @param array<string, mixed> $baseOperation @param array<string, mixed> $candidateOperation */
    private function compareOperation(string $path, array $baseOperation, array $candidateOperation, string $location): void
    {
        $this->compareSecurity(
            $baseOperation['security'] ?? $this->base['security'] ?? [],
            $candidateOperation['security'] ?? $this->candidate['security'] ?? [],
            $location,
        );
        $this->compareParameters(
            array_merge($this->pathParameters($this->base, $path), $baseOperation['parameters'] ?? []),
            array_merge($this->pathParameters($this->candidate, $path), $candidateOperation['parameters'] ?? []),
            $location,
        );
        $this->compareRequestBody($baseOperation['requestBody'] ?? null, $candidateOperation['requestBody'] ?? null, $location);
        $this->compareResponses($baseOperation['responses'] ?? [], $candidateOperation['responses'] ?? [], $location);
    }

    private function compareSecurity(mixed $base, mixed $candidate, string $location): void
    {
        if (! is_array($base) || ! is_array($candidate)) {
            $this->changes[] = "{$location}: security requirements became incompatible.";

            return;
        }

        $base = $base === [] ? [[]] : $base;
        $candidate = $candidate === [] ? [[]] : $candidate;

        foreach ($base as $baseAlternative) {
            if (! is_array($baseAlternative)) {
                continue;
            }
            foreach ($candidate as $candidateAlternative) {
                if (is_array($candidateAlternative) && $this->securityAlternativeAccepts($baseAlternative, $candidateAlternative)) {
                    continue 2;
                }
            }
            $this->changes[] = "{$location}: security requirements became more restrictive.";

            return;
        }
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function securityAlternativeAccepts(array $base, array $candidate): bool
    {
        foreach ($candidate as $scheme => $candidateScopes) {
            if (! array_key_exists($scheme, $base)) {
                return false;
            }
            $baseScopes = is_array($base[$scheme]) ? $base[$scheme] : [];
            $candidateScopes = is_array($candidateScopes) ? $candidateScopes : [];
            if (array_diff($candidateScopes, $baseScopes) !== []) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, mixed> $baseParameters @param array<int, mixed> $candidateParameters */
    private function compareParameters(array $baseParameters, array $candidateParameters, string $location): void
    {
        $base = $this->indexParameters($baseParameters, $this->baseDocument);
        $candidate = $this->indexParameters($candidateParameters, $this->candidateDocument);

        foreach ($base as $key => $baseParameter) {
            if (! isset($candidate[$key])) {
                $this->changes[] = "{$location}: parameter {$key} was removed.";

                continue;
            }

            $candidateParameter = $candidate[$key];
            foreach (['style', 'explode', 'allowReserved'] as $keyword) {
                if (($baseParameter[$keyword] ?? null) !== ($candidateParameter[$keyword] ?? null)) {
                    $this->changes[] = "{$location}: parameter {$key} changed {$keyword}.";
                }
            }
            if (($baseParameter['required'] ?? false) === false && ($candidateParameter['required'] ?? false) === true) {
                $this->changes[] = "{$location}: parameter {$key} became required.";
            }
            $this->compareParameterContent($baseParameter, $candidateParameter, "{$location} parameter {$key}");
        }

        foreach ($candidate as $key => $candidateParameter) {
            if (! isset($base[$key]) && ($candidateParameter['required'] ?? false) === true) {
                $this->changes[] = "{$location}: required parameter {$key} was added.";
            }
        }
    }

    /** @param array<int, mixed> $parameters @return array<string, array<string, mixed>> */
    private function indexParameters(array $parameters, OpenApiDocument $document): array
    {
        $indexed = [];

        foreach ($parameters as $parameter) {
            $parameter = $document->resolve($parameter);
            if (! is_array($parameter) || ! isset($parameter['name'], $parameter['in'])) {
                continue;
            }
            $name = $parameter['in'] === 'header' ? strtolower((string) $parameter['name']) : (string) $parameter['name'];
            $indexed[$parameter['in'].':'.$name] = $parameter;
        }

        return $indexed;
    }

    /** @param array<string, mixed> $specification @return array<int, mixed> */
    private function pathParameters(array $specification, string $path): array
    {
        $parameters = $specification['paths'][$path]['parameters'] ?? [];

        return is_array($parameters) ? $parameters : [];
    }

    private function compareRequestBody(mixed $baseBody, mixed $candidateBody, string $location): void
    {
        if ($baseBody === null) {
            $candidateBody = $this->candidateDocument->resolve($candidateBody);
            if (is_array($candidateBody) && ($candidateBody['required'] ?? false) === true) {
                $this->changes[] = "{$location}: a required request body was added.";
            }

            return;
        }
        if ($candidateBody === null) {
            $this->changes[] = "{$location}: request body was removed.";

            return;
        }

        $baseBody = $this->baseDocument->resolve($baseBody);
        $candidateBody = $this->candidateDocument->resolve($candidateBody);
        if (! is_array($baseBody) || ! is_array($candidateBody)) {
            $this->changes[] = "{$location}: request body became incompatible.";

            return;
        }
        if (($baseBody['required'] ?? false) === false && ($candidateBody['required'] ?? false) === true) {
            $this->changes[] = "{$location}: request body became required.";
        }
        $this->compareContent($baseBody['content'] ?? [], $candidateBody['content'] ?? [], 'request', "{$location} request body");
    }

    /** @param array<string, mixed> $baseResponses @param array<string, mixed> $candidateResponses */
    private function compareResponses(array $baseResponses, array $candidateResponses, string $location): void
    {
        foreach ($baseResponses as $status => $baseResponse) {
            if (! array_key_exists($status, $candidateResponses)) {
                $this->changes[] = "{$location}: response status {$status} was removed.";

                continue;
            }
            $baseResponse = $this->baseDocument->resolve($baseResponse);
            $candidateResponse = $this->candidateDocument->resolve($candidateResponses[$status]);
            if (! is_array($baseResponse) || ! is_array($candidateResponse)) {
                $this->changes[] = "{$location}: response {$status} became incompatible.";

                continue;
            }
            $this->compareResponseHeaders($baseResponse['headers'] ?? [], $candidateResponse['headers'] ?? [], "{$location} response {$status}");
            $this->compareContent($baseResponse['content'] ?? [], $candidateResponse['content'] ?? [], 'response', "{$location} response {$status}");
        }
    }

    /** @param array<string, mixed> $baseHeaders @param array<string, mixed> $candidateHeaders */
    private function compareResponseHeaders(array $baseHeaders, array $candidateHeaders, string $location): void
    {
        $candidateHeaders = array_change_key_case($candidateHeaders, CASE_LOWER);
        foreach ($baseHeaders as $name => $baseHeader) {
            $key = strtolower((string) $name);
            if (! array_key_exists($key, $candidateHeaders)) {
                $this->changes[] = "{$location}: header {$name} was removed.";

                continue;
            }
            $baseHeader = $this->baseDocument->resolve($baseHeader);
            $candidateHeader = $this->candidateDocument->resolve($candidateHeaders[$key]);
            if (is_array($baseHeader) && is_array($candidateHeader)) {
                $this->compareParameterContent($baseHeader, $candidateHeader, "{$location} header {$name}", 'response');
            }
        }
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function compareParameterContent(array $base, array $candidate, string $location, string $direction = 'request'): void
    {
        if (isset($base['schema']) || isset($candidate['schema'])) {
            $this->compareSchema($base['schema'] ?? true, $candidate['schema'] ?? true, $direction, $location.' schema');
        }
        if (isset($base['content']) || isset($candidate['content'])) {
            $this->compareContent($base['content'] ?? [], $candidate['content'] ?? [], $direction, $location);
        }
    }

    private function compareContent(mixed $baseContent, mixed $candidateContent, string $direction, string $location): void
    {
        if (! is_array($baseContent) || ! is_array($candidateContent)) {
            $this->changes[] = "{$location}: content declaration became incompatible.";

            return;
        }
        foreach ($baseContent as $mediaType => $baseMedia) {
            if (! array_key_exists($mediaType, $candidateContent)) {
                $this->changes[] = "{$location}: media type {$mediaType} was removed.";

                continue;
            }
            $candidateMedia = $candidateContent[$mediaType];
            if (is_array($baseMedia) && is_array($candidateMedia)) {
                $this->compareSchema($baseMedia['schema'] ?? true, $candidateMedia['schema'] ?? true, $direction, "{$location} {$mediaType}");
            }
        }
    }

    private function compareSchema(mixed $base, mixed $candidate, string $direction, string $location): void
    {
        array_push($this->changes, ...$this->schemas->breakingChanges($base, $candidate, $direction, $location));
    }
}
