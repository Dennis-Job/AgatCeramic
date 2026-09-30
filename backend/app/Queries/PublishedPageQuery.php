<?php

namespace App\Queries;

use App\Models\Page;
use Illuminate\Pagination\LengthAwarePaginator;

class PublishedPageQuery
{
    public function __construct(private readonly PageBlockContentQuery $blocks) {}

    /** @return array<string, mixed> */
    public function get(string $slug): array
    {
        return $this->project(Page::query()->where('published_slug', $slug)->where('is_published', true)->whereNotNull('published_snapshot')->firstOrFail());
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function listing(): LengthAwarePaginator
    {
        $pages = Page::query()->where('is_published', true)->whereNotNull('published_snapshot')->orderBy('id')->paginate(25);

        return new LengthAwarePaginator($pages->getCollection()->map(fn (Page $page): array => $this->project($page)),
            $pages->total(), $pages->perPage(), $pages->currentPage(), ['path' => $pages->path()]);
    }

    /** @return array<string, mixed> */
    public function project(Page $page): array
    {
        $snapshot = $page->published_snapshot;
        if ($snapshot === null) {
            throw new \LogicException('Published page must have a snapshot.');
        }
        /** @var list<array{id: string, type: string, enabled: bool, data: array<string, mixed>}> $contentBlocks */
        $contentBlocks = $snapshot['blocks'];
        /** @var array<string, mixed> $seo */
        $seo = $snapshot['seo'];
        /** @var array<string, mixed> $layout */
        $layout = $snapshot['site_layout'] ?? [];

        return [
            'id' => $page->id, 'slug' => $page->published_slug, 'title' => $snapshot['title'], 'body' => $snapshot['body'],
            'blocks' => $this->blocks->resolve($contentBlocks), 'seo' => $this->blocks->seo($seo),
            'is_published' => true, 'published_at' => $page->published_at?->toAtomString(),
            'created_at' => $page->created_at?->toAtomString(), 'updated_at' => $page->published_at?->toAtomString(),
            'site_layout' => $this->blocks->seo($layout),
        ];
    }
}
