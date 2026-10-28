<?php

namespace App\Support;

final class SiteFooter
{
    /**
     * @param  array<string, mixed>  $footer
     * @param  array<string, mixed>  $header
     * @return array<string, mixed>
     */
    public static function resolve(array $footer, array $header): array
    {
        // Legacy footers followed the header. An explicitly saved empty list
        // must stay empty instead of silently restoring the header links.
        if (! array_key_exists('navigation_links', $footer)) {
            $links = [];
            $seen = [];
            /** @var list<array{label: string, to?: string, children?: list<array{label: string, to: string}>}> $navigation */
            $navigation = $header['navigation'] ?? [];
            foreach ($navigation as $item) {
                foreach ($item['children'] ?? [$item] as $link) {
                    if (isset($link['to']) && ! isset($seen[$link['to']])) {
                        $links[] = ['label' => $link['label'], 'to' => $link['to']];
                        $seen[$link['to']] = true;
                    }
                }
            }
            $footer['navigation_links'] = $links;
        }
        $footer['social_links'] ??= [];
        $footer['bottom_right_url'] ??= '';

        return $footer;
    }
}
