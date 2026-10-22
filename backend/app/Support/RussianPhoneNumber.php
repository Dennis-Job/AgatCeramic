<?php

namespace App\Support;

final class RussianPhoneNumber
{
    public const PATTERN = '/^\+7[0-9]{10}$/D';

    public static function format(string $value): string
    {
        return preg_replace('/^\+7([0-9]{3})([0-9]{3})([0-9]{2})([0-9]{2})$/D', '+7 ($1) $2-$3-$4', $value) ?? $value;
    }

    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\+?[0-9()\s-]+$/D', trim($value))) {
            return null;
        }

        $compact = preg_replace('/[()\s-]/', '', trim($value));
        if ($compact === null) {
            return null;
        }

        if (preg_match('/^[0-9]{10}$/D', $compact)) {
            return '+7'.$compact;
        }

        if (preg_match('/^8[0-9]{10}$/D', $compact)) {
            return '+7'.substr($compact, 1);
        }

        return preg_match(self::PATTERN, $compact) ? $compact : null;
    }
}
