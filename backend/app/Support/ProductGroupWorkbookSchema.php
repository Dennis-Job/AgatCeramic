<?php

namespace App\Support;

final class ProductGroupWorkbookSchema
{
    public const ROLE_SLOTS = 20;

    /** @return list<string> */
    public static function legacyHeaders(): array
    {
        return [
            'Действие', 'Ключ группы', 'Исходный код', 'Код группы', 'Название',
            ...array_map(static fn (int $number): string => 'Ось '.$number, range(1, self::ROLE_SLOTS)),
        ];
    }

    /** @return list<string> */
    public static function headers(): array
    {
        return [
            ...self::legacyHeaders(),
            ...array_map(static fn (int $number): string => 'Индивидуальная '.$number, range(1, self::ROLE_SLOTS)),
        ];
    }
}
