<?php

declare(strict_types=1);

namespace AgatCeramic\OpenApi;

final class SchemaCompatibilityChecker
{
    /** @var array<int, string> */
    private array $changes = [];

    /** @var array<string, true> */
    private array $visitedPairs = [];

    public function __construct(
        private readonly OpenApiDocument $base,
        private readonly OpenApiDocument $candidate,
    ) {}

    /** @return array<int, string> */
    public function breakingChanges(mixed $baseSchema, mixed $candidateSchema, string $direction, string $location): array
    {
        $this->changes = [];
        $this->visitedPairs = [];
        $this->compare($baseSchema, $candidateSchema, $direction, $location);

        return array_values(array_unique($this->changes));
    }

    private function compare(mixed $baseSchema, mixed $candidateSchema, string $direction, string $location): void
    {
        if ($this->repairsDanglingReference($baseSchema, $candidateSchema)) {
            return;
        }

        $pair = $direction.'|'.$this->identity($baseSchema).'|'.$this->identity($candidateSchema);
        if (isset($this->visitedPairs[$pair])) {
            return;
        }
        $this->visitedPairs[$pair] = true;

        $baseSchema = $this->base->resolve($baseSchema);
        $candidateSchema = $this->candidate->resolve($candidateSchema);
        if (is_bool($baseSchema) || is_bool($candidateSchema)) {
            if ($this->booleanSchemaBreaks($baseSchema, $candidateSchema, $direction)) {
                $this->changes[] = "{$location}: schema acceptance became incompatible.";
            }

            return;
        }
        if (! is_array($baseSchema) || ! is_array($candidateSchema)) {
            $this->changes[] = "{$location}: schema became incompatible.";

            return;
        }

        $this->compareTypes($baseSchema, $candidateSchema, $direction, $location);
        $this->compareLiterals($baseSchema, $candidateSchema, $direction, $location);
        foreach (['format', 'contentEncoding', 'contentMediaType'] as $keyword) {
            $this->compareScalarConstraint($baseSchema, $candidateSchema, $keyword, $direction, $location);
        }
        foreach (['minimum', 'exclusiveMinimum', 'minLength', 'minItems', 'minProperties', 'minContains'] as $keyword) {
            $this->compareBound($baseSchema[$keyword] ?? null, $candidateSchema[$keyword] ?? null, true, $direction, $location, $keyword);
        }
        foreach (['maximum', 'exclusiveMaximum', 'maxLength', 'maxItems', 'maxProperties', 'maxContains'] as $keyword) {
            $this->compareBound($baseSchema[$keyword] ?? null, $candidateSchema[$keyword] ?? null, false, $direction, $location, $keyword);
        }
        $this->comparePattern($baseSchema, $candidateSchema, $direction, $location);
        $this->compareMultipleOf($baseSchema, $candidateSchema, $direction, $location);
        $this->compareUniqueItems($baseSchema, $candidateSchema, $direction, $location);
        $this->compareProperties($baseSchema, $candidateSchema, $direction, $location);
        $this->compareAdditionalProperties($baseSchema, $candidateSchema, $direction, $location);

        foreach (['items', 'contains', 'propertyNames', 'unevaluatedProperties'] as $keyword) {
            if (array_key_exists($keyword, $baseSchema) || array_key_exists($keyword, $candidateSchema)) {
                $this->compare($baseSchema[$keyword] ?? true, $candidateSchema[$keyword] ?? true, $direction, $location.' '.$keyword);
            }
        }
        foreach (['allOf', 'anyOf', 'oneOf'] as $keyword) {
            $this->compareComposition($baseSchema[$keyword] ?? [], $candidateSchema[$keyword] ?? [], $keyword, $direction, $location);
        }
        foreach (['not', 'discriminator'] as $keyword) {
            if (($baseSchema[$keyword] ?? null) !== ($candidateSchema[$keyword] ?? null)) {
                $this->changes[] = "{$location}: {$keyword} changed.";
            }
        }
    }

    private function repairsDanglingReference(mixed $base, mixed $candidate): bool
    {
        return is_array($base)
            && is_array($candidate)
            && isset($base['$ref'], $candidate['$ref'])
            && is_string($base['$ref'])
            && $base['$ref'] === $candidate['$ref']
            && ! $this->base->hasPointer($base['$ref']);
    }

    private function booleanSchemaBreaks(mixed $base, mixed $candidate, string $direction): bool
    {
        if ($direction === 'request') {
            if ($base === false) {
                return false;
            }
            if ($base === true) {
                return $candidate !== true;
            }

            return $candidate === false;
        }
        if ($base === true) {
            return false;
        }
        if ($base === false) {
            return $candidate !== false;
        }

        return $candidate === true;
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function compareTypes(array $base, array $candidate, string $direction, string $location): void
    {
        $baseTypes = $this->types($base);
        $candidateTypes = $this->types($candidate);
        $source = $direction === 'request' ? $baseTypes : $candidateTypes;
        $accepted = $direction === 'request' ? $candidateTypes : $baseTypes;

        foreach ($source as $type) {
            if (! in_array('*', $accepted, true)
                && ! in_array($type, $accepted, true)
                && ! ($type === 'integer' && in_array('number', $accepted, true))) {
                $this->changes[] = "{$location}: type/nullable changed incompatibly (".implode('|', $baseTypes).' -> '.implode('|', $candidateTypes).').';

                return;
            }
        }
    }

    /** @param array<string, mixed> $schema @return array<int, string> */
    private function types(array $schema): array
    {
        if (! array_key_exists('type', $schema)) {
            return ['*'];
        }

        $types = array_map('strval', is_array($schema['type']) ? $schema['type'] : [$schema['type']]);
        if (($schema['nullable'] ?? false) === true && ! in_array('null', $types, true)) {
            $types[] = 'null';
        }

        return array_values($types);
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function compareLiterals(array $base, array $candidate, string $direction, string $location): void
    {
        $baseValues = $this->literalValues($base);
        $candidateValues = $this->literalValues($candidate);
        if ($baseValues === null && $candidateValues === null) {
            return;
        }

        if ($direction === 'request') {
            if ($baseValues === null) {
                $this->changes[] = "{$location}: enum/const constraint was added to a request.";

                return;
            }
            foreach ($candidateValues === null ? [] : $this->strictDifference($baseValues, $candidateValues) as $value) {
                $this->changes[] = "{$location}: request enum value ".json_encode($value, JSON_UNESCAPED_UNICODE).' was removed.';
            }

            return;
        }

        if ($candidateValues === null) {
            $this->changes[] = "{$location}: response enum/const constraint was removed.";

            return;
        }
        foreach ($baseValues === null ? [] : $this->strictDifference($candidateValues, $baseValues) as $value) {
            $this->changes[] = "{$location}: response enum value ".json_encode($value, JSON_UNESCAPED_UNICODE).' was added.';
        }
    }

    /** @param array<string, mixed> $schema @return array<int, mixed>|null */
    private function literalValues(array $schema): ?array
    {
        if (array_key_exists('const', $schema)) {
            return [$schema['const']];
        }

        return isset($schema['enum']) && is_array($schema['enum']) ? array_values($schema['enum']) : null;
    }

    /** @param array<int, mixed> $values @param array<int, mixed> $accepted @return array<int, mixed> */
    private function strictDifference(array $values, array $accepted): array
    {
        return array_values(array_filter(
            $values,
            static fn (mixed $value): bool => ! in_array($value, $accepted, true),
        ));
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function compareScalarConstraint(array $base, array $candidate, string $keyword, string $direction, string $location): void
    {
        $baseValue = $base[$keyword] ?? null;
        $candidateValue = $candidate[$keyword] ?? null;
        if ($baseValue !== $candidateValue
            && (($direction === 'request' && $candidateValue !== null) || ($direction === 'response' && $baseValue !== null))) {
            $this->changes[] = "{$location}: {$keyword} changed incompatibly.";
        }
    }

    private function compareBound(mixed $base, mixed $candidate, bool $lower, string $direction, string $location, string $keyword): void
    {
        if ($base === $candidate) {
            return;
        }

        $baseNumber = is_int($base) || is_float($base) ? (float) $base : null;
        $candidateNumber = is_int($candidate) || is_float($candidate) ? (float) $candidate : null;
        $breaksRequest = $candidateNumber !== null && ($baseNumber === null || ($lower ? $candidateNumber > $baseNumber : $candidateNumber < $baseNumber));
        $breaksResponse = $baseNumber !== null && ($candidateNumber === null || ($lower ? $candidateNumber < $baseNumber : $candidateNumber > $baseNumber));

        if (($direction === 'request' && $breaksRequest) || ($direction === 'response' && $breaksResponse)) {
            $this->changes[] = "{$location}: {$keyword} changed incompatibly.";
        }
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function comparePattern(array $base, array $candidate, string $direction, string $location): void
    {
        $basePattern = $base['pattern'] ?? null;
        $candidatePattern = $candidate['pattern'] ?? null;
        if ($basePattern !== $candidatePattern && (($direction === 'request' && $candidatePattern !== null) || ($direction === 'response' && $basePattern !== null))) {
            $this->changes[] = "{$location}: pattern changed incompatibly.";
        }
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function compareMultipleOf(array $base, array $candidate, string $direction, string $location): void
    {
        $baseValue = $base['multipleOf'] ?? null;
        $candidateValue = $candidate['multipleOf'] ?? null;
        if ($baseValue === $candidateValue) {
            return;
        }

        $breaks = $direction === 'request'
            ? $candidateValue !== null && ($baseValue === null || ! $this->isMultiple((float) $baseValue, (float) $candidateValue))
            : $baseValue !== null && ($candidateValue === null || ! $this->isMultiple((float) $candidateValue, (float) $baseValue));
        if ($breaks) {
            $this->changes[] = "{$location}: multipleOf changed incompatibly.";
        }
    }

    private function isMultiple(float $value, float $divisor): bool
    {
        if ($divisor <= 0) {
            return false;
        }

        $quotient = $value / $divisor;

        return abs($quotient - round($quotient)) < 0.000000001;
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function compareUniqueItems(array $base, array $candidate, string $direction, string $location): void
    {
        $baseUnique = $base['uniqueItems'] ?? false;
        $candidateUnique = $candidate['uniqueItems'] ?? false;
        if (($direction === 'request' && ! $baseUnique && $candidateUnique)
            || ($direction === 'response' && $baseUnique && ! $candidateUnique)) {
            $this->changes[] = "{$location}: uniqueItems changed incompatibly.";
        }
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function compareProperties(array $base, array $candidate, string $direction, string $location): void
    {
        $baseRequired = is_array($base['required'] ?? null) ? $base['required'] : [];
        $candidateRequired = is_array($candidate['required'] ?? null) ? $candidate['required'] : [];
        $delta = $direction === 'request' ? array_diff($candidateRequired, $baseRequired) : array_diff($baseRequired, $candidateRequired);
        foreach ($delta as $property) {
            $action = $direction === 'request' ? 'became required' : 'is no longer guaranteed in the response';
            $this->changes[] = "{$location}: property {$property} {$action}.";
        }

        $baseProperties = is_array($base['properties'] ?? null) ? $base['properties'] : [];
        $candidateProperties = is_array($candidate['properties'] ?? null) ? $candidate['properties'] : [];
        foreach ($baseProperties as $name => $baseProperty) {
            if (! array_key_exists($name, $candidateProperties)) {
                $this->changes[] = "{$location}: property {$name} was removed.";

                continue;
            }
            $this->compare($baseProperty, $candidateProperties[$name], $direction, "{$location}.{$name}");
        }

        if ($direction === 'response' && ($base['additionalProperties'] ?? true) === false) {
            foreach (array_diff(array_keys($candidateProperties), array_keys($baseProperties)) as $name) {
                $this->changes[] = "{$location}: response property {$name} was added although the previous schema disallowed additional properties.";
            }
        }
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $candidate */
    private function compareAdditionalProperties(array $base, array $candidate, string $direction, string $location): void
    {
        $baseAdditional = $base['additionalProperties'] ?? true;
        $candidateAdditional = $candidate['additionalProperties'] ?? true;
        if ($direction === 'request' && $baseAdditional !== false && $candidateAdditional === false) {
            $this->changes[] = "{$location}: additional request properties are no longer accepted.";

            return;
        }
        if ($direction === 'response' && $baseAdditional === false && $candidateAdditional !== false) {
            $this->changes[] = "{$location}: response may now contain additional properties.";

            return;
        }
        if (is_array($baseAdditional) || is_array($candidateAdditional)) {
            $this->compare($baseAdditional, $candidateAdditional, $direction, $location.' additionalProperties');
        }
    }

    private function compareComposition(mixed $base, mixed $candidate, string $keyword, string $direction, string $location): void
    {
        $base = is_array($base) ? array_values($base) : [];
        $candidate = is_array($candidate) ? array_values($candidate) : [];
        $breaks = $keyword === 'allOf'
            ? ($direction === 'request' ? count($candidate) > count($base) : count($candidate) < count($base))
            : ($direction === 'request' ? count($candidate) < count($base) : count($candidate) > count($base));
        if ($breaks) {
            $this->changes[] = "{$location}: {$keyword} alternatives changed incompatibly.";
        }

        [$base, $candidate] = $this->removeIdenticalAlternatives($base, $candidate);
        foreach (array_intersect_key($base, $candidate) as $index => $baseItem) {
            $this->compare($baseItem, $candidate[$index], $direction, "{$location} {$keyword}[{$index}]");
        }
    }

    /**
     * @param  array<int, mixed>  $base
     * @param  array<int, mixed>  $candidate
     * @return array{array<int, mixed>, array<int, mixed>}
     */
    private function removeIdenticalAlternatives(array $base, array $candidate): array
    {
        foreach ($base as $baseIndex => $baseItem) {
            foreach ($candidate as $candidateIndex => $candidateItem) {
                if ($baseItem === $candidateItem) {
                    unset($base[$baseIndex], $candidate[$candidateIndex]);

                    continue 2;
                }
            }
        }

        return [array_values($base), array_values($candidate)];
    }

    private function identity(mixed $schema): string
    {
        if (is_array($schema) && isset($schema['$ref'])) {
            return (string) $schema['$ref'];
        }

        return hash('sha256', serialize($schema));
    }
}
