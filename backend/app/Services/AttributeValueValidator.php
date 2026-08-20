<?php

namespace App\Services;

class AttributeValueValidator
{
    /** @param array<int, string> $optionValues */
    public function isValid(string $type, mixed $value, array $optionValues = []): bool
    {
        return match ($type) {
            'text' => is_string($value) && mb_strlen($value) <= 10000,
            'number' => is_numeric($value),
            'boolean' => is_bool($value),
            'select' => is_string($value) && in_array($value, $optionValues, true),
            'multiselect' => is_array($value) && $value !== [] && count($value) <= 500
                && collect($value)->every(static fn (mixed $option): bool => is_string($option))
                && count($value) === count(array_unique($value))
                && collect($value)->every(static fn (string $option): bool => in_array($option, $optionValues, true)),
            default => false,
        };
    }
}
