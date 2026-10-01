<?php

namespace App\Http\Resources;

use App\Models\Page;
use App\Support\PageBlocks;
use Illuminate\Http\Request;

/** @extends ApiResource<Page> */
class PageResource extends ApiResource
{
    /** @return array<string, mixed> */
    #[\Override]
    public function toArray(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'blocks' => $this->blocks,
            'seo' => $this->seo,
            'is_published' => $this->is_published,
            'has_unpublished_changes' => PageBlocks::hasUnpublishedChanges($page),
            'published_at' => $this->published_at?->toAtomString(),
            'created_at' => $this->created_at?->toAtomString(),
            'updated_at' => $this->updated_at?->toAtomString(),
        ];
    }
}
