<?php

namespace App\Queries;

use App\Models\Page;

class ContentPreviewQuery
{
    public function __construct(
        private readonly PageBlockContentQuery $content,
        private readonly SiteAppearanceQuery $appearance,
    ) {}

    /** @return array{page: array{title: string, slug: string, body: string, blocks: list<array{id: string, type: string, enabled: bool, data: array<string, mixed>}>, seo: array<string, mixed>}, appearance: array{header: array<string, mixed>, footer: array<string, mixed>}} */
    public function get(string $slug): array
    {
        // Legacy home edits are synchronized into this canonical saved draft.
        $page = Page::query()->where('slug', $slug)->firstOrFail();
        $appearance = $this->appearance->get(true);
        /** @var array<string, mixed> $header */
        $header = $appearance['header'];
        /** @var array<string, mixed> $footer */
        $footer = $appearance['footer'];

        return [
            'page' => [
                'title' => $page->title,
                'slug' => $page->slug,
                'body' => $page->body,
                'blocks' => $this->content->resolve($page->blocks),
                'seo' => $this->content->seo($page->seo),
            ],
            'appearance' => ['header' => $header, 'footer' => $footer],
        ];
    }
}
