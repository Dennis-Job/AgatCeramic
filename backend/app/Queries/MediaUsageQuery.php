<?php

namespace App\Queries;

use App\Models\HomePage;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaUsageQuery
{
    public function isReferenced(Media $media, ?HomePage $homePage): bool
    {
        $mediaId = $media->id;

        return DB::table('categories')->where('image_id', $mediaId)->exists()
            || DB::table('brands')->where('logo_id', $mediaId)->exists()
            || DB::table('banners')->where('image_media_id', $mediaId)->exists()
            || DB::table('brand_media_documents')->where('media_id', $mediaId)->exists()
            || DB::table('category_media_documents')->where('media_id', $mediaId)->exists()
            || DB::table('page_media')->where('media_id', $mediaId)->exists()
            || ($homePage !== null && in_array($mediaId, $homePage->referencedMediaIds(), true))
            || $this->isReferencedByUrl($media, $homePage);
    }

    private function isReferencedByUrl(Media $media, ?HomePage $homePage): bool
    {
        $paths = [];
        foreach (array_filter([$media->path, $media->thumbnail_path]) as $path) {
            $paths[] = $this->urlPath(Storage::disk($media->disk)->url($path));
        }
        if ($homePage !== null && $this->containsFileUrl($homePage->content, $paths)) {
            return true;
        }
        foreach (DB::table('banners')->whereNotNull('image_url')->pluck('image_url') as $url) {
            if (is_string($url) && in_array($this->urlPath($url), $paths, true)) {
                return true;
            }
        }
        // Drafts and published snapshots can both reference original or thumbnail URLs.
        foreach (Page::query()->select(['id', 'blocks', 'seo', 'site_layout', 'published_snapshot'])->cursor() as $page) {
            if ($this->containsFileUrl([$page->blocks, $page->seo, $page->site_layout, $page->published_snapshot], $paths)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $content  Nested JSON content with scalar/array values.
     * @param  list<string>  $paths
     */
    private function containsFileUrl(array $content, array $paths): bool
    {
        foreach ($content as $value) {
            if (is_array($value) && $this->containsFileUrl($value, $paths)) {
                return true;
            }
            if (is_string($value) && in_array($this->urlPath($value), $paths, true)) {
                return true;
            }
        }

        return false;
    }

    private function urlPath(string $url): string
    {
        // Relative and absolute site URLs may differ in host, query or percent encoding.
        return rawurldecode((string) parse_url($url, PHP_URL_PATH));
    }
}
