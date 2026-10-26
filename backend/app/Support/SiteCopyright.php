<?php

namespace App\Support;

final class SiteCopyright
{
    public static function text(): string
    {
        /** @var string $timezone */
        $timezone = config('app.timezone');

        return '© '.now($timezone)->format('Y').' AgatCeramic';
    }
}
