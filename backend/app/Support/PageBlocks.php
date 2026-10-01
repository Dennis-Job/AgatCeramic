<?php

namespace App\Support;

use App\Models\Page;

class PageBlocks
{
    /** @return array<string, mixed> */
    public static function snapshot(Page $page): array
    {
        $snapshot = ['title' => $page->title, 'slug' => $page->slug, 'body' => $page->body, 'blocks' => $page->blocks, 'seo' => $page->seo];
        if ($page->site_layout !== null) {
            $snapshot['site_layout'] = $page->site_layout;
        }

        return $snapshot;
    }

    public static function hasUnpublishedChanges(Page $page): bool
    {
        $published = $page->published_snapshot;
        $draft = self::snapshot($page);
        unset($published['site_layout'], $draft['site_layout']);

        return $published !== $draft;
    }

    public const HOME_TYPES = ['hero', 'marquee', 'categories', 'materials', 'promo', 'about', 'guide'];

    public const TYPES = [...self::HOME_TYPES, 'text', 'stores', 'catalog'];

    public const RESERVED_SLUGS = ['home', 'contacts', 'about', 'catalog'];

    /** @param array<string, mixed> $content
     * @return list<array{id: string, type: string, enabled: bool, data: array<string, mixed>}>
     */
    public static function home(array $content, ?int $sliderId): array
    {
        $blocks = [];
        foreach (self::HOME_TYPES as $type) {
            $data = $type === 'hero' ? ['slider_id' => $sliderId] : ($content[$type] ?? []);
            if (! is_array($data)) {
                throw new \LogicException('Home page sections must be objects.');
            }
            /** @var array<string, mixed> $data */
            $blocks[] = ['id' => $type, 'type' => $type, 'enabled' => true, 'data' => $data];
        }

        return $blocks;
    }

    /**
     * @param  array<mixed>  $value
     * @return list<int>
     */
    public static function mediaIds(array $value): array
    {
        $ids = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $ids = [...$ids, ...self::mediaIds($item)];
            } elseif (is_string($key) && str_ends_with($key, '_media_id') && is_int($item)) {
                $ids[] = $item;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return array<string, mixed> */
    public static function defaultSeo(string $title, string $description = ''): array
    {
        return ['title' => $title, 'description' => $description, 'og_title' => $title, 'og_description' => $description, 'og_image_url' => null, 'og_image_media_id' => null];
    }
}
