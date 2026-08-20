<?php

namespace App\Services;

use DateTimeImmutable;

class AttributeValueValidator
{
    /** @param array<int, string> $optionValues */
    public function isValid(string $type, mixed $value, array $optionValues = []): bool
    {
        return match ($type) {
            'string' => is_string($value) && $value !== '' && mb_strlen($value) <= 255,
            'text' => is_string($value) && $value !== '' && mb_strlen($value) <= 10000,
            'integer' => is_int($value),
            'decimal' => (is_int($value) || is_float($value)) && is_finite((float) $value),
            'boolean' => is_bool($value),
            'select' => is_string($value) && in_array($value, $optionValues, true),
            'multiselect' => is_array($value) && $value !== [] && count($value) <= 500
                && collect($value)->every(static fn (mixed $option): bool => is_string($option))
                && count($value) === count(array_unique($value))
                && collect($value)->every(static fn (string $option): bool => in_array($option, $optionValues, true)),
            'date' => is_string($value) && $this->isIsoDate($value),
            default => false,
        };
    }

    private function isIsoDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
