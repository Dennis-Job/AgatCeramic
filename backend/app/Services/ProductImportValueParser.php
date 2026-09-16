<?php

namespace App\Services;

use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ProductImportValueParser
{
    public function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public function requiredString(mixed $value, int $row, string $field): string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            $this->rowError($row, "столбец {$field} должен быть заполнен.");
        }

        return $value;
    }

    public function nullableInteger(mixed $value, int $row, string $field): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            return (int) trim($value);
        }
        $this->rowError($row, "столбец {$field} должен содержать целое число.");
    }

    public function requiredInteger(mixed $value, int $row, string $field): int
    {
        $value = $this->nullableInteger($value, $row, $field);
        if ($value === null) {
            $this->rowError($row, "столбец {$field} должен быть заполнен.");
        }

        return $value;
    }

    public function decimal(mixed $value, int $row, string $field): float
    {
        if (! is_numeric($value)) {
            $this->rowError($row, "столбец {$field} должен содержать число.");
        }

        return (float) $value;
    }

    public function boolean(mixed $value, int $row, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === 1.0 || $value === '1' || (is_string($value) && in_array(mb_strtolower(trim($value)), ['true', 'да'], true))) {
            return true;
        }
        if ($value === 0 || $value === 0.0 || $value === '0' || (is_string($value) && in_array(mb_strtolower(trim($value)), ['false', 'нет'], true))) {
            return false;
        }
        $this->rowError($row, "столбец {$field} должен содержать Да/Нет, true/false или 1/0.");
    }

    /** @return list<string> */
    public function multiselect(mixed $value, int $row, string $field): array
    {
        if (! is_string($value)) {
            $this->rowError($row, "столбец {$field} должен содержать JSON-массив строк.");
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->rowError($row, "столбец {$field} должен содержать корректный JSON-массив строк.");
        }
        if (! is_array($decoded) || ! array_is_list($decoded) || collect($decoded)->contains(fn (mixed $item): bool => ! is_string($item))) {
            $this->rowError($row, "столбец {$field} должен содержать JSON-массив строк.");
        }

        /** @var list<string> $decoded */
        return $decoded;
    }

    public function date(mixed $value, int $row, string $field): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $this->requiredString($value, $row, $field);
    }

    public function rowError(int $row, string $message): never
    {
        throw ValidationException::withMessages(['file' => ["Строка {$row}: {$message}"]]);
    }
}
