<?php

declare(strict_types=1);

namespace AgatCeramic\OpenApi;

use RuntimeException;

final class OpenApiDocument
{
    /** @param array<string, mixed> $specification */
    public function __construct(private readonly array $specification) {}

    /** @return array<string, mixed> */
    public function specification(): array
    {
        return $this->specification;
    }

    public function resolve(mixed $object): mixed
    {
        if (! is_array($object) || ! isset($object['$ref']) || ! is_string($object['$ref'])) {
            return $object;
        }

        $resolved = $this->resolvePointer($object['$ref']);
        if (! is_array($resolved)) {
            return $resolved;
        }

        unset($object['$ref']);

        return array_replace_recursive($resolved, $object);
    }

    public function hasPointer(string $reference): bool
    {
        try {
            $this->resolvePointer($reference);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function resolvePointer(string $reference): mixed
    {
        if (! str_starts_with($reference, '#/')) {
            throw new RuntimeException("Compatibility checker only accepts local references; semantic lint must bundle external references first: {$reference}");
        }

        $value = $this->specification;
        foreach (explode('/', substr($reference, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                throw new RuntimeException("Unresolved OpenAPI reference {$reference}.");
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
