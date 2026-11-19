<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use DateTimeImmutable;

/** Shared boundaries for emitted options and their selectable query values. */
final class CatalogAttributeOptionValue
{
    public static function maximumLength(string $type): int
    {
        return match ($type) {
            'text' => 10000,
            'integer', 'decimal' => 1024,
            default => 255,
        };
    }

    public static function normalize(string $type, string $value): ?string
    {
        if (str_contains($value, "\0") || trim($value) === '' || mb_strlen($value) > self::maximumLength($type)) {
            return null;
        }
        if ($type === 'boolean') {
            return in_array($value, ['true', 'false'], true) ? $value : null;
        }
        if ($type === 'date') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
                return null;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
        }
        if (! in_array($type, ['integer', 'decimal'], true)) {
            return $value;
        }
        // Historical numeric strings are supported using JSON number syntax, never PHP coercion.
        if (preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d{1,3})?$/D', $value) !== 1) {
            return null;
        }
        $number = BigDecimal::of($value)->strippedOfTrailingZeros();
        if ($type === 'integer') {
            if ($number->getScale() > 0 || $number->isLessThan(PHP_INT_MIN) || $number->isGreaterThan(PHP_INT_MAX)) {
                return null;
            }
        } elseif ($number->abs()->isGreaterThan('1.7976931348623157e308') || (! $number->isZero() && $number->abs()->isLessThan('5e-324'))) {
            return null;
        }
        $canonical = (string) $number;

        return strlen($canonical) <= self::maximumLength($type) ? $canonical : null;
    }
}
