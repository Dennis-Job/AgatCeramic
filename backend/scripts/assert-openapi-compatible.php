<?php

declare(strict_types=1);

/**
 * Rejects unversioned breaking changes to the published OpenAPI contract.
 *
 * Usage: php scripts/assert-openapi-compatible.php <base-spec> <candidate-spec>
 */
if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/assert-openapi-compatible.php <base-spec> <candidate-spec>\n");
    exit(2);
}

/** @return array<string, mixed> */
function loadSpecification(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Cannot read {$path}.");
    }

    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
}

/** @return array<string, array<string, array<string, mixed>>> */
function operations(array $specification): array
{
    $operations = [];

    foreach ($specification['paths'] ?? [] as $path => $pathItem) {
        foreach ($pathItem as $method => $operation) {
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                $operations[$path][$method] = $operation;
            }
        }
    }

    return $operations;
}

/** @return array<int, string> */
function requiredProperties(array $schema): array
{
    return $schema['required'] ?? [];
}

/** @param array<int, string> $breakingChanges */
function compareSchema(array $base, array $candidate, string $location, array &$breakingChanges): void
{
    foreach (requiredProperties($base) as $property) {
        if (! in_array($property, requiredProperties($candidate), true)) {
            $breakingChanges[] = "{$location}: required property '{$property}' was removed.";
        }
    }

    if (isset($base['enum'])) {
        $candidateValues = $candidate['enum'] ?? [];
        foreach ($base['enum'] as $value) {
            if (! in_array($value, $candidateValues, true)) {
                $breakingChanges[] = "{$location}: enum value '".json_encode($value, JSON_UNESCAPED_UNICODE)."' was removed.";
            }
        }
    }
}

try {
    $base = loadSpecification($argv[1]);
    $candidate = loadSpecification($argv[2]);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(2);
}

$breakingChanges = [];
$baseOperations = operations($base);
$candidateOperations = operations($candidate);

foreach ($baseOperations as $path => $methods) {
    foreach ($methods as $method => $baseOperation) {
        $candidateOperation = $candidateOperations[$path][$method] ?? null;

        if ($candidateOperation === null) {
            $breakingChanges[] = strtoupper($method)." {$path}: operation was removed.";

            continue;
        }

        if (($baseOperation['security'] ?? []) !== ($candidateOperation['security'] ?? [])) {
            $breakingChanges[] = strtoupper($method)." {$path}: security requirements changed.";
        }

        foreach (($baseOperation['responses'] ?? []) as $status => $baseResponse) {
            if (! isset($candidateOperation['responses'][$status])) {
                $breakingChanges[] = strtoupper($method)." {$path}: response {$status} was removed.";

                continue;
            }

            foreach (($baseResponse['content'] ?? []) as $mediaType => $baseMedia) {
                $candidateMedia = $candidateOperation['responses'][$status]['content'][$mediaType] ?? null;

                if ($candidateMedia === null) {
                    $breakingChanges[] = strtoupper($method)." {$path}: response {$status} no longer supports {$mediaType}.";

                    continue;
                }

                if (($baseMedia['schema']['$ref'] ?? null) !== ($candidateMedia['schema']['$ref'] ?? null)) {
                    $breakingChanges[] = strtoupper($method)." {$path}: response {$status} schema changed.";
                }
            }
        }

        $baseRequired = $baseOperation['requestBody']['required'] ?? false;
        $candidateRequired = $candidateOperation['requestBody']['required'] ?? false;
        if (! $baseRequired && $candidateRequired) {
            $breakingChanges[] = strtoupper($method)." {$path}: request body became required.";
        }

        foreach (($baseOperation['requestBody']['content'] ?? []) as $mediaType => $baseMedia) {
            if (! isset($candidateOperation['requestBody']['content'][$mediaType])) {
                $breakingChanges[] = strtoupper($method)." {$path}: request body no longer supports {$mediaType}.";
            }
        }
    }
}

foreach (($base['components']['schemas'] ?? []) as $name => $baseSchema) {
    $candidateSchema = $candidate['components']['schemas'][$name] ?? null;

    if ($candidateSchema === null) {
        $breakingChanges[] = "Schema {$name} was removed.";

        continue;
    }

    compareSchema($baseSchema, $candidateSchema, "Schema {$name}", $breakingChanges);
}

if ($breakingChanges === []) {
    exit(0);
}

$baseVersion = $base['info']['version'] ?? null;
$candidateVersion = $candidate['info']['version'] ?? null;

if ($baseVersion !== $candidateVersion) {
    fwrite(STDOUT, "OpenAPI contains intentional breaking changes with a version bump ({$baseVersion} -> {$candidateVersion}):\n");
    foreach ($breakingChanges as $change) {
        fwrite(STDOUT, "- {$change}\n");
    }
    exit(0);
}

fwrite(STDERR, "OpenAPI breaking changes require an info.version bump and migration plan:\n");
foreach ($breakingChanges as $change) {
    fwrite(STDERR, "- {$change}\n");
}
exit(1);
