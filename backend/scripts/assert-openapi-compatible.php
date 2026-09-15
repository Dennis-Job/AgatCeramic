<?php

declare(strict_types=1);

use AgatCeramic\OpenApi\CompatibilityChecker;
use AgatCeramic\OpenApi\CompatibilityPolicy;

require_once __DIR__.'/openapi/OpenApiDocument.php';
require_once __DIR__.'/openapi/SchemaCompatibilityChecker.php';
require_once __DIR__.'/openapi/CompatibilityChecker.php';
require_once __DIR__.'/openapi/CompatibilityPolicy.php';

/**
 * Usage: php scripts/assert-openapi-compatible.php <base-spec> <candidate-spec>
 *        [--migration-plan <path>]
 */
if ($argc !== 3 && $argc !== 5) {
    fwrite(STDERR, "Usage: php scripts/assert-openapi-compatible.php <base-spec> <candidate-spec> [--migration-plan <path>]\n");
    exit(2);
}

if ($argc === 5 && $argv[3] !== '--migration-plan') {
    fwrite(STDERR, "Expected --migration-plan before the migration plan path.\n");
    exit(2);
}

/** @return array<string, mixed> */
function loadSpecification(string $path): array
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException("Cannot read {$path}.");
    }

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($decoded)) {
        throw new RuntimeException("OpenAPI specification {$path} must be a JSON object.");
    }

    return $decoded;
}

try {
    $base = loadSpecification($argv[1]);
    $candidate = loadSpecification($argv[2]);
    $breakingChanges = (new CompatibilityChecker($base, $candidate))->breakingChanges();

    if ($breakingChanges === []) {
        fwrite(STDOUT, "OpenAPI contracts are backward compatible.\n");
        exit(0);
    }

    $policyError = (new CompatibilityPolicy)->breakingChangePolicyError(
        (string) ($base['info']['version'] ?? ''),
        (string) ($candidate['info']['version'] ?? ''),
        $argc === 5 ? $argv[4] : null,
    );

    if ($policyError === null) {
        fwrite(STDOUT, "OpenAPI contains an approved breaking major release:\n");
        foreach ($breakingChanges as $change) {
            fwrite(STDOUT, "- {$change}\n");
        }
        exit(0);
    }

    fwrite(STDERR, "OpenAPI contains breaking changes. {$policyError}\n");
    foreach ($breakingChanges as $change) {
        fwrite(STDERR, "- {$change}\n");
    }
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(2);
}
