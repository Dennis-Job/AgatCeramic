<?php

namespace App\Support;

final class SiteHeader
{
    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $footer
     * @return array<string, mixed>
     */
    public static function resolve(array $header, array $footer): array
    {
        // Existing drawers showed the footer tagline. An explicitly cleared
        // note disables it and must not restore that historical fallback.
        if (! array_key_exists('menu_note', $header)) {
            $header['menu_note'] = $footer['tagline'] ?? '';
        }
        $header['menu_note'] ??= '';

        return $header;
    }
}
