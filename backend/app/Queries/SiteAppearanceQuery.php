<?php

namespace App\Queries;

use App\Models\Page;
use App\Support\SiteCopyright;
use App\Support\SiteFooter;

class SiteAppearanceQuery
{
    public function __construct(private readonly PageBlockContentQuery $content) {}

    /** @return array<string, mixed> */
    public function get(bool $admin = false): array
    {
        // The legacy home row hosts one global draft and publication, independently
        // of whether the home page itself is currently published.
        $page = Page::query()->where('slug', 'home')->firstOrFail();
        /** @var array<string, mixed> $published */
        $published = $page->published_snapshot['site_layout'] ?? [];
        $layout = $admin ? ($page->site_layout ?? []) : $published;
        $resolved = $this->content->seo($layout);
        /** @var array<string, mixed> $header */
        $header = $resolved['header'];
        $header['logo_url'] ??= null;
        /** @var array<string, mixed> $footer */
        $footer = $resolved['footer'];
        $footer = SiteFooter::resolve($footer, $header);
        $footer['bottom_left'] = SiteCopyright::text();
        $result = ['header' => $header, 'footer' => $footer];

        return $admin ? [...$result, 'has_unpublished_changes' => $page->site_layout !== $published] : $result;
    }
}
